<?php

declare(strict_types=1);

use DynamikDev\Cloak\Detector;

it('cloak helper function works', function () {
    $text = 'Email: test@example.com';
    $result = cloak($text);

    expect($result)->toMatch('/Email: \{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
});

it('uncloak helper function works', function () {
    $text = 'Email: test@example.com';
    $cloaked = cloak($text);
    $uncloaked = uncloak($cloaked);

    expect($uncloaked)->toBe($text);
});

it('cloak helper accepts custom detectors', function () {
    $text = 'Email: test@example.com SSN: 123-45-6789';
    $result = cloak($text, [Detector::email()]);

    expect($result)->toMatch('/\{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
    expect($result)->toContain('123-45-6789');
});

it('helpers use default ArrayStore', function () {
    $text = 'test@example.com';
    $cloaked = cloak($text);

    expect($cloaked)->toMatch('/\{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/');
    expect(uncloak($cloaked))->toBe($text);
});
