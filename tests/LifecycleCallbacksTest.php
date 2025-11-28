<?php

declare(strict_types=1);

use DynamikDev\Cloak\Cloak;
use DynamikDev\Cloak\Detector;
use DynamikDev\Cloak\Stores\ArrayStore;

it('executes beforeCloak callback', function () {
    $store = new ArrayStore();
    $called = false;

    $cloak = Cloak::using($store)
        ->beforeCloak(function ($text) use (&$called) {
            $called = true;

            return $text;
        });

    $cloak->cloak('test@example.com', [Detector::email()]);

    expect($called)->toBeTrue();
});

it('beforeCloak callback can modify text', function () {
    $store = new ArrayStore();

    $cloak = Cloak::using($store)
        ->beforeCloak(fn ($text) => strtoupper($text));

    $result = $cloak->cloak('test@example.com', [Detector::email()]);
    $uncloaked = $cloak->uncloak($result);

    // Email was uppercased before detection
    expect($uncloaked)->toBe('TEST@EXAMPLE.COM');
});

it('executes afterCloak callback', function () {
    $store = new ArrayStore();
    $called = false;
    $receivedOriginal = '';
    $receivedCloaked = '';

    $cloak = Cloak::using($store)
        ->afterCloak(function ($original, $cloaked) use (&$called, &$receivedOriginal, &$receivedCloaked) {
            $called = true;
            $receivedOriginal = $original;
            $receivedCloaked = $cloaked;
        });

    $result = $cloak->cloak('test@example.com', [Detector::email()]);

    expect($called)->toBeTrue();
    expect($receivedOriginal)->toBe('test@example.com');
    expect($receivedCloaked)->toBe($result);
});

it('executes beforeUncloak callback', function () {
    $store = new ArrayStore();
    $called = false;

    $cloak = Cloak::using($store)
        ->beforeUncloak(function ($text) use (&$called) {
            $called = true;

            return $text;
        });

    $cloaked = $cloak->cloak('test@example.com', [Detector::email()]);
    $cloak->uncloak($cloaked);

    expect($called)->toBeTrue();
});

it('beforeUncloak callback can modify text', function () {
    $store = new ArrayStore();

    $cloak = Cloak::using($store)
        ->beforeUncloak(fn ($text) => trim($text));

    $cloaked = $cloak->cloak('test@example.com', [Detector::email()]);
    $result = $cloak->uncloak('  ' . $cloaked . '  ');

    expect($result)->toBe('test@example.com');
});

it('executes afterUncloak callback', function () {
    $store = new ArrayStore();
    $called = false;

    $cloak = Cloak::using($store)
        ->afterUncloak(function ($text) use (&$called) {
            $called = true;

            return $text;
        });

    $cloaked = $cloak->cloak('test@example.com', [Detector::email()]);
    $cloak->uncloak($cloaked);

    expect($called)->toBeTrue();
});

it('afterUncloak callback can modify result', function () {
    $store = new ArrayStore();

    $cloak = Cloak::using($store)
        ->afterUncloak(fn ($text) => strtoupper($text));

    $cloaked = $cloak->cloak('test@example.com', [Detector::email()]);
    $result = $cloak->uncloak($cloaked);

    expect($result)->toBe('TEST@EXAMPLE.COM');
});

it('executes multiple callbacks in registration order', function () {
    $store = new ArrayStore();
    $order = [];

    $cloak = Cloak::using($store)
        ->beforeCloak(function ($text) use (&$order) {
            $order[] = 'before1';

            return $text;
        })
        ->beforeCloak(function ($text) use (&$order) {
            $order[] = 'before2';

            return $text;
        });

    $cloak->cloak('test@example.com', [Detector::email()]);

    expect($order)->toBe(['before1', 'before2']);
});

it('chains beforeCloak transformations', function () {
    $store = new ArrayStore();

    $cloak = Cloak::using($store)
        ->beforeCloak(fn ($text) => strtoupper($text))
        ->beforeCloak(fn ($text) => str_replace('@', '[AT]', $text));

    $result = $cloak->cloak('test@example.com', [Detector::email()]);
    $uncloaked = $cloak->uncloak($result);

    expect($uncloaked)->toBe('TEST[AT]EXAMPLE.COM');
});

it('chains afterUncloak transformations', function () {
    $store = new ArrayStore();

    $cloak = Cloak::using($store)
        ->afterUncloak(fn ($text) => str_replace('@', ' [at] ', $text))
        ->afterUncloak(fn ($text) => strtoupper($text));

    $cloaked = $cloak->cloak('test@example.com', [Detector::email()]);
    $result = $cloak->uncloak($cloaked);

    expect($result)->toBe('TEST [AT] EXAMPLE.COM');
});

it('combines builder methods with callbacks', function () {
    $store = new ArrayStore();
    $log = [];

    $cloak = Cloak::using($store)
        ->withDetectors([Detector::email()])
        ->withTtl(7200)
        ->filter(fn ($d) => !str_ends_with($d['match'], '.local'))
        ->beforeCloak(function ($text) use (&$log) {
            $log[] = 'before';

            return $text;
        })
        ->afterCloak(function ($original, $cloaked) use (&$log) {
            $log[] = 'after';
        });

    $result = $cloak->cloak('test@example.com local@test.local');

    expect($log)->toBe(['before', 'after']);
    expect($result)->toMatch('/\{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
    expect($result)->toContain('local@test.local');
});

it('executes callbacks even when no detections found', function () {
    $store = new ArrayStore();
    $beforeCalled = false;
    $afterCalled = false;

    $cloak = Cloak::using($store)
        ->beforeCloak(function ($text) use (&$beforeCalled) {
            $beforeCalled = true;

            return $text;
        })
        ->afterCloak(function ($original, $cloaked) use (&$afterCalled) {
            $afterCalled = true;
        });

    $result = $cloak->cloak('No sensitive data', [Detector::email()]);

    expect($beforeCalled)->toBeTrue();
    expect($afterCalled)->toBeTrue();
    expect($result)->toBe('No sensitive data');
});

it('executes callbacks even when all detections filtered', function () {
    $store = new ArrayStore();
    $beforeCalled = false;
    $afterCalled = false;

    $cloak = Cloak::using($store)
        ->filter(fn () => false)
        ->beforeCloak(function ($text) use (&$beforeCalled) {
            $beforeCalled = true;

            return $text;
        })
        ->afterCloak(function ($original, $cloaked) use (&$afterCalled) {
            $afterCalled = true;
        });

    $result = $cloak->cloak('test@example.com', [Detector::email()]);

    expect($beforeCalled)->toBeTrue();
    expect($afterCalled)->toBeTrue();
    expect($result)->toBe('test@example.com');
});
