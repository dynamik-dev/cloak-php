<?php

declare(strict_types=1);

use DynamikDev\Cloak\Contracts\DetectorInterface;
use DynamikDev\Cloak\Detectors\SSN;

it('implements DetectorInterface', function () {
    $detector = new SSN();
    expect($detector)->toBeInstanceOf(DetectorInterface::class);
});

it('detects SSN format', function () {
    $detector = new SSN();
    $result = $detector->detect('SSN: 123-45-6789');

    expect($result)->toBe([
        ['match' => '123-45-6789', 'type' => 'ssn'],
    ]);
});

it('detects multiple SSNs', function () {
    $detector = new SSN();
    $result = $detector->detect('Person A: 111-22-3333 Person B: 444-55-6666');

    expect($result)->toBe([
        ['match' => '111-22-3333', 'type' => 'ssn'],
        ['match' => '444-55-6666', 'type' => 'ssn'],
    ]);
});

it('returns empty array when no SSNs found', function () {
    $detector = new SSN();
    $result = $detector->detect('No SSN here!');

    expect($result)->toBe([]);
});

it('does not match invalid SSN formats', function () {
    $detector = new SSN();
    $result = $detector->detect('Not SSN: 123-456-789 or 12-34-5678');

    expect($result)->toBe([]);
});
