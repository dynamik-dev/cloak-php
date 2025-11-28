<?php

declare(strict_types=1);

use DynamikDev\Cloak\Encryptors\OpenSslEncryptor;

beforeEach(function () {
    putenv('CLOAK_TEST_PRIVATE_KEY');
    unset($_ENV['CLOAK_TEST_PRIVATE_KEY']);
});

it('implements EncryptorInterface', function () {
    $key = OpenSslEncryptor::generateKey();
    $encryptor = new OpenSslEncryptor($key);

    expect($encryptor)->toBeInstanceOf(DynamikDev\Cloak\Contracts\EncryptorInterface::class);
});

it('generates a valid base64 key', function () {
    $key = OpenSslEncryptor::generateKey();

    expect($key)->toBeString();
    expect(base64_decode($key, true))->not->toBe(false);
    expect(strlen(base64_decode($key, true)))->toBe(32);
});

it('encrypts and decrypts successfully', function () {
    $key = OpenSslEncryptor::generateKey();
    $encryptor = new OpenSslEncryptor($key);
    $value = 'test@example.com';

    $encrypted = $encryptor->encrypt($value);
    $decrypted = $encryptor->decrypt($encrypted);

    expect($decrypted)->toBe($value);
    expect($encrypted)->not->toBe($value);
});

it('produces different output for same input', function () {
    $key = OpenSslEncryptor::generateKey();
    $encryptor = new OpenSslEncryptor($key);
    $value = 'test@example.com';

    $encrypted1 = $encryptor->encrypt($value);
    $encrypted2 = $encryptor->encrypt($value);

    // Different due to random IV
    expect($encrypted1)->not->toBe($encrypted2);

    // But both decrypt to same value
    expect($encryptor->decrypt($encrypted1))->toBe($value);
    expect($encryptor->decrypt($encrypted2))->toBe($value);
});

it('accepts base64-encoded key', function () {
    $key = OpenSslEncryptor::generateKey();
    $encryptor = new OpenSslEncryptor($key);
    $value = 'test@example.com';

    $encrypted = $encryptor->encrypt($value);
    $decrypted = $encryptor->decrypt($encrypted);

    expect($decrypted)->toBe($value);
});

it('accepts raw 32-byte key', function () {
    $rawKey = random_bytes(32);
    $encryptor = new OpenSslEncryptor($rawKey);
    $value = 'test@example.com';

    $encrypted = $encryptor->encrypt($value);
    $decrypted = $encryptor->decrypt($encrypted);

    expect($decrypted)->toBe($value);
});

it('throws exception for invalid key length', function () {
    new OpenSslEncryptor('short-key');
})->throws(RuntimeException::class, 'Invalid encryption key');

it('throws exception for tampered ciphertext', function () {
    $key = OpenSslEncryptor::generateKey();
    $encryptor = new OpenSslEncryptor($key);

    $encrypted = $encryptor->encrypt('test');
    $tampered = substr($encrypted, 0, -5) . 'XXXXX';

    $encryptor->decrypt($tampered);
})->throws(RuntimeException::class);

it('throws exception for invalid base64', function () {
    $key = OpenSslEncryptor::generateKey();
    $encryptor = new OpenSslEncryptor($key);

    $encryptor->decrypt('not-valid-base64!!!');
})->throws(RuntimeException::class, 'invalid base64');

it('throws exception for data too short', function () {
    $key = OpenSslEncryptor::generateKey();
    $encryptor = new OpenSslEncryptor($key);

    // Valid base64 but too short
    $encryptor->decrypt(base64_encode('short'));
})->throws(RuntimeException::class, 'too short');

it('handles empty strings', function () {
    $key = OpenSslEncryptor::generateKey();
    $encryptor = new OpenSslEncryptor($key);

    $encrypted = $encryptor->encrypt('');
    $decrypted = $encryptor->decrypt($encrypted);

    expect($decrypted)->toBe('');
});

it('handles long strings', function () {
    $key = OpenSslEncryptor::generateKey();
    $encryptor = new OpenSslEncryptor($key);
    $value = str_repeat('test@example.com ', 1000);

    $encrypted = $encryptor->encrypt($value);
    $decrypted = $encryptor->decrypt($encrypted);

    expect($decrypted)->toBe($value);
});

it('handles special characters', function () {
    $key = OpenSslEncryptor::generateKey();
    $encryptor = new OpenSslEncryptor($key);
    $value = '!@#$%^&*(){}[]|\\:;"\'<>,.?/~`';

    $encrypted = $encryptor->encrypt($value);
    $decrypted = $encryptor->decrypt($encrypted);

    expect($decrypted)->toBe($value);
});

it('handles unicode characters', function () {
    $key = OpenSslEncryptor::generateKey();
    $encryptor = new OpenSslEncryptor($key);
    $value = '你好世界 🌍 مرحبا עברית';

    $encrypted = $encryptor->encrypt($value);
    $decrypted = $encryptor->decrypt($encrypted);

    expect($decrypted)->toBe($value);
});

it('different keys produce different ciphertext', function () {
    $key1 = OpenSslEncryptor::generateKey();
    $key2 = OpenSslEncryptor::generateKey();
    $encryptor1 = new OpenSslEncryptor($key1);
    $encryptor2 = new OpenSslEncryptor($key2);
    $value = 'test@example.com';

    $encrypted1 = $encryptor1->encrypt($value);
    $encrypted2 = $encryptor2->encrypt($value);

    expect($encrypted1)->not->toBe($encrypted2);
});

it('cannot decrypt with wrong key', function () {
    $key1 = OpenSslEncryptor::generateKey();
    $key2 = OpenSslEncryptor::generateKey();
    $encryptor1 = new OpenSslEncryptor($key1);
    $encryptor2 = new OpenSslEncryptor($key2);

    $encrypted = $encryptor1->encrypt('test@example.com');

    $encryptor2->decrypt($encrypted);
})->throws(RuntimeException::class, 'authentication tag mismatch');

it('reads key from CLOAK_PRIVATE_KEY environment variable', function () {
    $key = OpenSslEncryptor::generateKey();
    putenv("CLOAK_TEST_PRIVATE_KEY={$key}");

    $encryptor = new OpenSslEncryptor(null, 'CLOAK_TEST_PRIVATE_KEY');
    $value = 'test@example.com';

    $encrypted = $encryptor->encrypt($value);
    $decrypted = $encryptor->decrypt($encrypted);

    expect($decrypted)->toBe($value);
});

it('reads key from $_ENV array', function () {
    $key = OpenSslEncryptor::generateKey();
    $_ENV['CLOAK_TEST_PRIVATE_KEY'] = $key;

    $encryptor = new OpenSslEncryptor(null, 'CLOAK_TEST_PRIVATE_KEY');
    $value = 'test@example.com';

    $encrypted = $encryptor->encrypt($value);
    $decrypted = $encryptor->decrypt($encrypted);

    expect($decrypted)->toBe($value);
});

it('throws exception when env var not set and no key provided', function () {
    new OpenSslEncryptor(null, 'CLOAK_TEST_PRIVATE_KEY');
})->throws(RuntimeException::class, 'CLOAK_TEST_PRIVATE_KEY environment variable is not set');

it('prefers provided key over environment variable', function () {
    $envKey = OpenSslEncryptor::generateKey();
    $providedKey = OpenSslEncryptor::generateKey();

    putenv("CLOAK_TEST_PRIVATE_KEY={$envKey}");

    $encryptor = new OpenSslEncryptor($providedKey, 'CLOAK_TEST_PRIVATE_KEY');
    $value = 'test@example.com';

    $encrypted = $encryptor->encrypt($value);

    $envEncryptor = new OpenSslEncryptor($envKey, 'CLOAK_TEST_PRIVATE_KEY');

    expect(fn () => $envEncryptor->decrypt($encrypted))
        ->toThrow(RuntimeException::class);
});
