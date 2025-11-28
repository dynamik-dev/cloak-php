<?php

declare(strict_types=1);

use DynamikDev\Cloak\Cloak;
use DynamikDev\Cloak\Detector;
use DynamikDev\Cloak\Stores\ArrayStore;

it('can chain withDetectors method', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withDetectors([Detector::email()]);

    expect($cloak)->toBeInstanceOf(Cloak::class);
});

it('uses default detectors when set via withDetectors', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withDetectors([Detector::email()]);

    $result = $cloak->cloak('test@example.com 123-45-6789');

    // Should only cloak email, not SSN
    expect($result)->toMatch('/\{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
    expect($result)->toContain('123-45-6789');
});

it('can chain multiple builder methods', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->withDetectors([Detector::email()])
        ->filter(fn ($d) => strlen($d['match']) > 5);

    expect($cloak)->toBeInstanceOf(Cloak::class);
});

it('filters out detections based on filter callback', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->filter(function ($detection) {
            // Exclude test.local emails
            if ($detection['type'] === 'email' && str_ends_with($detection['match'], '.local')) {
                return false;
            }

            return true;
        });

    $result = $cloak->cloak('Email: test@example.com and local@test.local', [Detector::email()]);

    // Should cloak first email but not second
    expect($result)->toMatch('/Email: \{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
    expect($result)->toContain('local@test.local');
});

it('applies multiple filters in sequence', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->filter(fn ($d) => strlen($d['match']) > 10) // Must be longer than 10 chars
        ->filter(fn ($d) => !str_contains($d['match'], 'test')); // Must not contain 'test'

    $result = $cloak->cloak('a@b.com test@example.com admin@company.com', [Detector::email()]);

    // a@b.com filtered out by length (7 chars, stays visible)
    // test@example.com filtered out by 'test' check (stays visible)
    // admin@company.com passes both filters (17 chars, gets cloaked)
    expect($result)->toContain('a@b.com');
    expect($result)->toContain('test@example.com');
    expect($result)->toMatch('/\{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
    expect($result)->not->toContain('admin@company.com');
});

it('can filter by type', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->filter(fn ($d) => $d['type'] !== 'ssn');

    $result = $cloak->cloak('Email: test@example.com SSN: 123-45-6789', [
        Detector::email(),
        Detector::ssn(),
    ]);

    expect($result)->toMatch('/Email: \{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
    expect($result)->toContain('123-45-6789');
});

it('returns original text when all detections are filtered', function () {
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->filter(fn () => false); // Filter out everything

    $result = $cloak->cloak('test@example.com', [Detector::email()]);

    expect($result)->toBe('test@example.com');
});

it('filters with whitelist pattern', function () {
    $whitelist = ['support@company.com', 'public@company.com'];
    $store = new ArrayStore();
    $cloak = Cloak::using($store)
        ->filter(fn ($d) => !in_array($d['match'], $whitelist));

    $result = $cloak->cloak('Contact: support@company.com or admin@company.com', [Detector::email()]);

    expect($result)->toContain('support@company.com');
    expect($result)->toMatch('/\{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
    expect($result)->not->toContain('admin@company.com');
});
