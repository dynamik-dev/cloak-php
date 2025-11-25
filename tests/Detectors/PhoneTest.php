<?php

declare(strict_types=1);

use DynamikDev\Cloak\Contracts\DetectorInterface;
use DynamikDev\Cloak\Detectors\Phone;

it('implements DetectorInterface', function () {
    $detector = new Phone();
    expect($detector)->toBeInstanceOf(DetectorInterface::class);
});

// US Phone Number Tests
it('detects US phone with dashes', function () {
    $detector = new Phone('US');
    $result = $detector->detect('Call me at 212-456-7890');

    expect($result)->toBe([
        ['match' => '212-456-7890', 'type' => 'phone'],
    ]);
});

it('detects US phone with dots', function () {
    $detector = new Phone('US');
    $result = $detector->detect('Phone: 212.456.7890');

    expect($result)->toBe([
        ['match' => '212.456.7890', 'type' => 'phone'],
    ]);
});

it('detects US phone without separators', function () {
    $detector = new Phone('US');
    $result = $detector->detect('Number: 2124567890');

    expect($result)->toBe([
        ['match' => '2124567890', 'type' => 'phone'],
    ]);
});

it('detects US phone with parentheses', function () {
    $detector = new Phone('US');
    $result = $detector->detect('Contact: (212) 456-7890');

    expect($result)->toBe([
        ['match' => '(212) 456-7890', 'type' => 'phone'],
    ]);
});

it('detects multiple US phone numbers', function () {
    $detector = new Phone('US');
    $result = $detector->detect('Home: 212-456-7890 Work: 718-555-1234');

    expect($result)->toBe([
        ['match' => '212-456-7890', 'type' => 'phone'],
        ['match' => '718-555-1234', 'type' => 'phone'],
    ]);
});

// International Phone Number Tests
it('detects UK phone numbers', function () {
    $detector = new Phone('GB');
    $result = $detector->detect('Ring me at 0117 496 0123');

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('phone');
    expect($result[0]['match'])->toBe('0117 496 0123');
});

it('detects international format with plus sign', function () {
    $detector = new Phone();
    $result = $detector->detect('Call +44 117 496 0123');

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('phone');
});

it('detects German phone numbers', function () {
    $detector = new Phone('DE');
    $result = $detector->detect('Telefon: 030 12345678');

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('phone');
});

// False Positive Tests
it('does not match order IDs', function () {
    $detector = new Phone('US');
    $result = $detector->detect('Order #123456789012345');

    expect($result)->toBe([]);
});

it('does not match long digit strings', function () {
    $detector = new Phone('US');
    $result = $detector->detect('Transaction: 99999999999999999999999');

    expect($result)->toBe([]);
});

it('does not match credit card numbers', function () {
    $detector = new Phone('US');
    $result = $detector->detect('Card: 4111111111111111');

    expect($result)->toBe([]);
});

it('does not match timestamps', function () {
    $detector = new Phone('US');
    $result = $detector->detect('Timestamp: 20231225123456');

    expect($result)->toBe([]);
});

it('does not match times like 1430', function () {
    $detector = new Phone('GB');
    $result = $detector->detect('Call me at 1430 today');

    expect($result)->toBe([]);
});

it('filters out time but keeps valid phone in same text', function () {
    $detector = new Phone('GB');
    $result = $detector->detect('Hi, can you ring me at 1430 on 0117 496 0123. Thanks!');

    expect($result)->toHaveCount(1);
    expect($result[0]['match'])->toBe('0117 496 0123');
});

it('does not match serial numbers', function () {
    $detector = new Phone('US');
    $result = $detector->detect('Serial: SN-987654321098');

    expect($result)->toBe([]);
});

// Edge Cases
it('returns empty array when no phones found', function () {
    $detector = new Phone();
    $result = $detector->detect('No phone numbers here!');

    expect($result)->toBe([]);
});

it('handles empty string', function () {
    $detector = new Phone();
    $result = $detector->detect('');

    expect($result)->toBe([]);
});

it('works without default region', function () {
    $detector = new Phone();
    $result = $detector->detect('Call +1 212-456-7890');

    expect($result)->toHaveCount(1);
    expect($result[0]['type'])->toBe('phone');
});
