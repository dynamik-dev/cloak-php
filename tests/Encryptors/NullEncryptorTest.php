<?php

declare(strict_types=1);

use DynamikDev\Cloak\Encryptors\NullEncryptor;

it('implements EncryptorInterface', function () {
    $encryptor = new NullEncryptor();

    expect($encryptor)->toBeInstanceOf(DynamikDev\Cloak\Contracts\EncryptorInterface::class);
});

it('returns value unchanged when encrypting', function () {
    $encryptor = new NullEncryptor();
    $value = 'test@example.com';

    $encrypted = $encryptor->encrypt($value);

    expect($encrypted)->toBe($value);
});

it('returns value unchanged when decrypting', function () {
    $encryptor = new NullEncryptor();
    $value = 'encrypted-data';

    $decrypted = $encryptor->decrypt($value);

    expect($decrypted)->toBe($value);
});

it('handles empty strings', function () {
    $encryptor = new NullEncryptor();

    expect($encryptor->encrypt(''))->toBe('');
    expect($encryptor->decrypt(''))->toBe('');
});

it('handles special characters', function () {
    $encryptor = new NullEncryptor();
    $value = '!@#$%^&*(){}[]|\\:;"\'<>,.?/~`';

    expect($encryptor->encrypt($value))->toBe($value);
    expect($encryptor->decrypt($value))->toBe($value);
});

it('handles unicode characters', function () {
    $encryptor = new NullEncryptor();
    $value = '你好世界 🌍 مرحبا';

    expect($encryptor->encrypt($value))->toBe($value);
    expect($encryptor->decrypt($value))->toBe($value);
});
