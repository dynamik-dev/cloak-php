<?php

declare(strict_types=1);

use DynamikDev\Cloak\Contracts\DetectorInterface;
use DynamikDev\Cloak\Detector;
use DynamikDev\Cloak\Detectors\CreditCard;
use DynamikDev\Cloak\Detectors\Email;
use DynamikDev\Cloak\Detectors\Phone;
use DynamikDev\Cloak\Detectors\SSN;

it('returns email detector', function () {
    $detector = Detector::email();
    expect($detector)->toBeInstanceOf(Email::class);
    expect($detector)->toBeInstanceOf(DetectorInterface::class);
});

it('returns phone detector', function () {
    $detector = Detector::phone();
    expect($detector)->toBeInstanceOf(Phone::class);
    expect($detector)->toBeInstanceOf(DetectorInterface::class);
});

it('returns ssn detector', function () {
    $detector = Detector::ssn();
    expect($detector)->toBeInstanceOf(SSN::class);
    expect($detector)->toBeInstanceOf(DetectorInterface::class);
});

it('returns credit card detector', function () {
    $detector = Detector::creditCard();
    expect($detector)->toBeInstanceOf(CreditCard::class);
    expect($detector)->toBeInstanceOf(DetectorInterface::class);
});

it('returns all built-in detectors', function () {
    $detectors = Detector::all();

    expect($detectors)->toBeArray();
    expect($detectors)->toHaveCount(4);
    expect($detectors[0])->toBeInstanceOf(Email::class);
    expect($detectors[1])->toBeInstanceOf(Phone::class);
    expect($detectors[2])->toBeInstanceOf(SSN::class);
    expect($detectors[3])->toBeInstanceOf(CreditCard::class);
});

it('creates custom pattern detector', function () {
    $detector = Detector::pattern('/\b[A-Z]{2}\d{6}\b/', 'passport');

    $result = $detector->detect('Passport: AB123456');

    expect($result)->toBe([
        ['match' => 'AB123456', 'type' => 'passport'],
    ]);
});

it('creates words detector', function () {
    $detector = Detector::words(['secret', 'confidential'], 'sensitive');

    $result = $detector->detect('This is secret and confidential info');

    expect($result)->toBe([
        ['match' => 'secret', 'type' => 'sensitive'],
        ['match' => 'confidential', 'type' => 'sensitive'],
    ]);
});

it('words detector is case insensitive', function () {
    $detector = Detector::words(['SECRET'], 'sensitive');

    $result = $detector->detect('This is secret info');

    expect($result)->toBe([
        ['match' => 'secret', 'type' => 'sensitive'],
    ]);
});

it('creates callable detector', function () {
    $detector = Detector::using(function (string $text): array {
        $matches = [];
        if (str_contains($text, 'custom')) {
            $matches[] = ['match' => 'custom', 'type' => 'custom_type'];
        }
        return $matches;
    });

    $result = $detector->detect('This has custom data');

    expect($result)->toBe([
        ['match' => 'custom', 'type' => 'custom_type'],
    ]);
});
