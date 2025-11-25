<?php

declare(strict_types=1);

use DynamikDev\Cloak\Contracts\DetectorInterface;
use DynamikDev\Cloak\Detectors\Email;

it('implements DetectorInterface', function () {
    $detector = new Email();
    expect($detector)->toBeInstanceOf(DetectorInterface::class);
});

it('detects simple email addresses', function () {
    $detector = new Email();
    $result = $detector->detect('Contact me at test@example.com');

    expect($result)->toBe([
        ['match' => 'test@example.com', 'type' => 'email'],
    ]);
});

it('detects email with subdomain', function () {
    $detector = new Email();
    $result = $detector->detect('Email: user@mail.example.co.uk');

    expect($result)->toBe([
        ['match' => 'user@mail.example.co.uk', 'type' => 'email'],
    ]);
});

it('detects email with plus addressing', function () {
    $detector = new Email();
    $result = $detector->detect('Send to foo.bar+tag@example.com');

    expect($result)->toBe([
        ['match' => 'foo.bar+tag@example.com', 'type' => 'email'],
    ]);
});

it('detects multiple emails', function () {
    $detector = new Email();
    $result = $detector->detect('From: a@b.com To: c@d.org');

    expect($result)->toBe([
        ['match' => 'a@b.com', 'type' => 'email'],
        ['match' => 'c@d.org', 'type' => 'email'],
    ]);
});

it('returns empty array when no emails found', function () {
    $detector = new Email();
    $result = $detector->detect('No emails here!');

    expect($result)->toBe([]);
});

it('ignores invalid email formats', function () {
    $detector = new Email();
    $result = $detector->detect('Not valid: user@ or @domain.com or user@.com');

    expect($result)->toBe([]);
});
