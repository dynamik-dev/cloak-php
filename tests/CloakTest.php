<?php

declare(strict_types=1);

use DynamikDev\Cloak\Cloak;
use DynamikDev\Cloak\Detector;
use DynamikDev\Cloak\Stores\ArrayStore;

it('creates instance with store', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    expect($cloak)->toBeInstanceOf(Cloak::class);
});

it('cloaks email and returns placeholder', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $result = $cloak->cloak('Contact: test@example.com', [Detector::email()]);

    expect($result)->toMatch('/Contact: \{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
});

it('generates 6 character alphanumeric key', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $result = $cloak->cloak('Email: test@example.com', [Detector::email()]);

    preg_match('/\{\{EMAIL_([a-zA-Z0-9]{6})_1\}\}/', $result, $matches);
    expect($matches[1])->toMatch('/^[a-zA-Z0-9]{6}$/');
});

it('placeholder follows format TYPE_KEY_INDEX', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $result = $cloak->cloak('test@example.com', [Detector::email()]);

    expect($result)->toMatch('/^\{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}$/');
});

it('same value gets same placeholder', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $result = $cloak->cloak('test@example.com and test@example.com', [Detector::email()]);

    preg_match_all('/\{\{EMAIL_[a-zA-Z0-9]{6}_\d+\}\}/', $result, $matches);
    expect($matches[0][0])->toBe($matches[0][1]);
});

it('different values get different indices', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $result = $cloak->cloak('a@b.com and c@d.com', [Detector::email()]);

    preg_match_all('/\{\{EMAIL_([a-zA-Z0-9]{6})_(\d+)\}\}/', $result, $matches);
    expect($matches[2][0])->toBe('1');
    expect($matches[2][1])->toBe('2');
    // Same key for both
    expect($matches[1][0])->toBe($matches[1][1]);
});

it('returns original text when no detections', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $result = $cloak->cloak('No sensitive data here', [Detector::email()]);

    expect($result)->toBe('No sensitive data here');
});

it('stores mapping in store', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $result = $cloak->cloak('test@example.com', [Detector::email()]);

    preg_match('/\{\{EMAIL_([a-zA-Z0-9]{6})_1\}\}/', $result, $matches);
    $key = 'cloak:' . $matches[1];
    $stored = $store->get($key);

    expect($stored)->toBeArray();
    expect($stored)->toHaveKey($result);
    expect($stored[$result])->toBe('test@example.com');
});

it('uncloaks placeholder back to original', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $cloaked = $cloak->cloak('Contact: test@example.com', [Detector::email()]);
    $uncloaked = $cloak->uncloak($cloaked);

    expect($uncloaked)->toBe('Contact: test@example.com');
});

it('uncloaks multiple placeholders', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $cloaked = $cloak->cloak('Email: a@b.com Phone: 555-123-4567', [
        Detector::email(),
        Detector::phone(),
    ]);
    $uncloaked = $cloak->uncloak($cloaked);

    expect($uncloaked)->toBe('Email: a@b.com Phone: 555-123-4567');
});

it('leaves placeholder intact when cache missing', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $result = $cloak->uncloak('Unknown: {{EMAIL_abc123_1}}');

    expect($result)->toBe('Unknown: {{EMAIL_abc123_1}}');
});

it('handles multiple types in same string', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $text = 'Email: test@example.com SSN: 123-45-6789';
    $cloaked = $cloak->cloak($text, [Detector::email(), Detector::ssn()]);

    expect($cloaked)->toMatch('/Email: \{\{EMAIL_[a-zA-Z0-9]{6}_1\}\} SSN: \{\{SSN_[a-zA-Z0-9]{6}_1\}\}/');

    $uncloaked = $cloak->uncloak($cloaked);
    expect($uncloaked)->toBe($text);
});

it('uses all detectors when none specified', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $result = $cloak->cloak('test@example.com 212-456-7890');

    expect($result)->toMatch('/\{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
    expect($result)->toMatch('/\{\{PHONE_[a-zA-Z0-9]{6}_1\}\}/');
});

it('handles text with no placeholders in uncloak', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $result = $cloak->uncloak('Plain text without placeholders');

    expect($result)->toBe('Plain text without placeholders');
});

it('handles credit card with underscored type', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store);

    $result = $cloak->cloak('Card: 4111111111111111', [Detector::creditCard()]);

    expect($result)->toMatch('/Card: \{\{CREDIT_CARD_[a-zA-Z0-9]{6}_1\}\}/');
});

it('uses custom resolver when set', function () {
    $customStore = new ArrayStore();
    $customCloak = Cloak::using($customStore);

    Cloak::resolveUsing(fn() => $customCloak);

    $resolved = Cloak::make();

    expect($resolved)->toBe($customCloak);

    Cloak::clearResolver();
});

it('resolver is called on every make() call', function () {
    $callCount = 0;

    Cloak::resolveUsing(function () use (&$callCount) {
        $callCount++;
        return Cloak::using(new ArrayStore());
    });

    Cloak::make();
    Cloak::make();
    Cloak::make();

    expect($callCount)->toBe(3);

    Cloak::clearResolver();
});

it('clearResolver reverts to default behavior', function () {
    $customStore = new ArrayStore();
    Cloak::resolveUsing(fn() => Cloak::using($customStore));

    Cloak::clearResolver();

    $resolved = Cloak::make();

    expect($resolved)->toBeInstanceOf(Cloak::class);
    expect($resolved)->not->toBe(Cloak::using($customStore));
});

it('make uses default store when no resolver set', function () {
    $cloak = Cloak::make();

    expect($cloak)->toBeInstanceOf(Cloak::class);
});
