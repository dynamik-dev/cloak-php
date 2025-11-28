<?php

declare(strict_types=1);

use DynamikDev\Cloak\Cloak;
use DynamikDev\Cloak\Detector;
use DynamikDev\Cloak\Encryptors\OpenSslEncryptor;
use DynamikDev\Cloak\Stores\ArrayStore;

beforeEach(function () {
    putenv('CLOAK_TEST_PRIVATE_KEY');
    unset($_ENV['CLOAK_TEST_PRIVATE_KEY']);
});

it('uses NullEncryptor by default', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $cloaked = $cloak->cloak('test@example.com', [Detector::email()]);
    preg_match('/\{\{EMAIL_([a-zA-Z0-9]{6})_1\}\}/', $cloaked, $matches);
    $key = 'cloak:' . $matches[1];

    // Value stored in plaintext
    $stored = $store->get($key);
    expect($stored)->toBeArray();
    expect($stored[$cloaked])->toBe('test@example.com');
});

it('encrypts values when withEncryptor() is called', function () {
    $encryptionKey = OpenSslEncryptor::generateKey();
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withEncryptor(new OpenSslEncryptor($encryptionKey));

    $cloaked = $cloak->cloak('test@example.com', [Detector::email()]);
    preg_match('/\{\{EMAIL_([a-zA-Z0-9]{6})_1\}\}/', $cloaked, $matches);
    $key = 'cloak:' . $matches[1];

    // Value stored encrypted
    $stored = $store->get($key);
    expect($stored)->toBeArray();
    expect($stored[$cloaked])->not->toBe('test@example.com');
});

it('decrypts values correctly on uncloak', function () {
    $encryptionKey = OpenSslEncryptor::generateKey();
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withEncryptor(new OpenSslEncryptor($encryptionKey));

    $original = 'Contact: test@example.com Phone: 555-123-4567';
    $cloaked = $cloak->cloak($original, [Detector::email(), Detector::phone()]);
    $uncloaked = $cloak->uncloak($cloaked);

    expect($uncloaked)->toBe($original);
});

it('uses custom encryptor via withEncryptor()', function () {
    $encryptionKey = OpenSslEncryptor::generateKey();
    $customEncryptor = new OpenSslEncryptor($encryptionKey);
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withEncryptor($customEncryptor);

    $original = 'test@example.com';
    $cloaked = $cloak->cloak($original, [Detector::email()]);
    $uncloaked = $cloak->uncloak($cloaked);

    expect($uncloaked)->toBe($original);
});


it('reads encryption key from environment variable', function () {
    $key = OpenSslEncryptor::generateKey();
    putenv("CLOAK_TEST_PRIVATE_KEY={$key}");

    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withEncryptor(new OpenSslEncryptor(null, 'CLOAK_TEST_PRIVATE_KEY'));

    $original = 'test@example.com';
    $cloaked = $cloak->cloak($original, [Detector::email()]);
    $uncloaked = $cloak->uncloak($cloaked);

    expect($uncloaked)->toBe($original);
});

it('encrypts multiple values independently', function () {
    $encryptionKey = OpenSslEncryptor::generateKey();
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withEncryptor(new OpenSslEncryptor($encryptionKey));

    $original = 'Email: test@example.com, Phone: 555-123-4567, SSN: 123-45-6789';
    $cloaked = $cloak->cloak($original, [
        Detector::email(),
        Detector::phone(),
        Detector::ssn(),
    ]);

    $uncloaked = $cloak->uncloak($cloaked);
    expect($uncloaked)->toBe($original);
});

it('handles same value appearing multiple times with encryption', function () {
    $encryptionKey = OpenSslEncryptor::generateKey();
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withEncryptor(new OpenSslEncryptor($encryptionKey));

    $original = 'test@example.com and test@example.com';
    $cloaked = $cloak->cloak($original, [Detector::email()]);

    // Should use same placeholder
    preg_match_all('/\{\{EMAIL_[a-zA-Z0-9]{6}_\d+\}\}/', $cloaked, $matches);
    expect($matches[0][0])->toBe($matches[0][1]);

    $uncloaked = $cloak->uncloak($cloaked);
    expect($uncloaked)->toBe($original);
});

it('combines encryption with filters', function () {
    $encryptionKey = OpenSslEncryptor::generateKey();
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withEncryptor(new OpenSslEncryptor($encryptionKey))
        ->filter(fn ($d) => !str_ends_with($d['match'], '.local'));

    $text = 'prod@company.com test@test.local';
    $cloaked = $cloak->cloak($text, [Detector::email()]);

    // test@test.local should not be cloaked (filtered out)
    expect($cloaked)->toMatch('/\{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
    expect($cloaked)->toContain('test@test.local');

    $uncloaked = $cloak->uncloak($cloaked);
    expect($uncloaked)->toContain('prod@company.com');
    expect($uncloaked)->toContain('test@test.local');
});

it('combines encryption with lifecycle callbacks', function () {
    $encryptionKey = OpenSslEncryptor::generateKey();
    $store = new ArrayStore();
    $log = [];

    $cloak = Cloak::using($store)
        ->withEncryptor(new OpenSslEncryptor($encryptionKey))
        ->beforeCloak(function ($text) use (&$log) {
            $log[] = 'before';

            return $text;
        })
        ->afterCloak(function ($original, $cloaked) use (&$log) {
            $log[] = 'after';
        });

    $original = 'test@example.com';
    $cloaked = $cloak->cloak($original, [Detector::email()]);
    $uncloaked = $cloak->uncloak($cloaked);

    expect($log)->toBe(['before', 'after']);
    expect($uncloaked)->toBe($original);
});

it('cannot decrypt with wrong key', function () {
    $key1 = OpenSslEncryptor::generateKey();
    $key2 = OpenSslEncryptor::generateKey();
    $store = new ArrayStore();

    $cloak1 = Cloak::using($store)->withEncryptor(new OpenSslEncryptor($key1));
    $cloaked = $cloak1->cloak('test@example.com', [Detector::email()]);

    $cloak2 = Cloak::using($store)->withEncryptor(new OpenSslEncryptor($key2));
    $cloak2->uncloak($cloaked);
})->throws(RuntimeException::class);

it('handles encryption with withTtl()', function () {
    $encryptionKey = OpenSslEncryptor::generateKey();
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withTtl(7200)
        ->withEncryptor(new OpenSslEncryptor($encryptionKey));

    $original = 'test@example.com';
    $cloaked = $cloak->cloak($original, [Detector::email()]);
    $uncloaked = $cloak->uncloak($cloaked);

    expect($uncloaked)->toBe($original);
});

it('handles encryption with withDetectors()', function () {
    $encryptionKey = OpenSslEncryptor::generateKey();
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withDetectors([Detector::email()])
        ->withEncryptor(new OpenSslEncryptor($encryptionKey));

    $text = 'Email: test@example.com Phone: 555-123-4567';
    $cloaked = $cloak->cloak($text);
    $uncloaked = $cloak->uncloak($cloaked);

    // Only email should be cloaked
    expect($uncloaked)->toContain('test@example.com');
    expect($uncloaked)->toContain('555-123-4567');
});

it('switching encryptors replaces the encryptor', function () {
    $key1 = OpenSslEncryptor::generateKey();
    $key2 = OpenSslEncryptor::generateKey();
    $store = new ArrayStore();

    $cloak = Cloak::using($store)
        ->withEncryptor(new OpenSslEncryptor($key1))
        ->withEncryptor(new OpenSslEncryptor($key2));

    $original = 'test@example.com';
    $cloaked = $cloak->cloak($original, [Detector::email()]);
    $uncloaked = $cloak->uncloak($cloaked);

    expect($uncloaked)->toBe($original);
});

it('handles empty encryption results', function () {
    $encryptionKey = OpenSslEncryptor::generateKey();
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withEncryptor(new OpenSslEncryptor($encryptionKey));

    $result = $cloak->cloak('No sensitive data here', [Detector::email()]);

    expect($result)->toBe('No sensitive data here');
});
