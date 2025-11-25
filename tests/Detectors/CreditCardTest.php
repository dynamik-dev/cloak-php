<?php

declare(strict_types=1);

use DynamikDev\Cloak\Contracts\DetectorInterface;
use DynamikDev\Cloak\Detectors\CreditCard;

it('implements DetectorInterface', function () {
    $detector = new CreditCard();
    expect($detector)->toBeInstanceOf(DetectorInterface::class);
});

it('detects credit card without separators', function () {
    $detector = new CreditCard();
    $result = $detector->detect('Card: 4111111111111111');

    expect($result)->toBe([
        ['match' => '4111111111111111', 'type' => 'credit_card'],
    ]);
});

it('detects credit card with dashes', function () {
    $detector = new CreditCard();
    $result = $detector->detect('Card: 4111-1111-1111-1111');

    expect($result)->toBe([
        ['match' => '4111-1111-1111-1111', 'type' => 'credit_card'],
    ]);
});

it('detects credit card with spaces', function () {
    $detector = new CreditCard();
    $result = $detector->detect('Card: 4111 1111 1111 1111');

    expect($result)->toBe([
        ['match' => '4111 1111 1111 1111', 'type' => 'credit_card'],
    ]);
});

it('detects multiple credit cards', function () {
    $detector = new CreditCard();
    $result = $detector->detect('Primary: 4111111111111111 Secondary: 5500-0000-0000-0004');

    expect($result)->toBe([
        ['match' => '4111111111111111', 'type' => 'credit_card'],
        ['match' => '5500-0000-0000-0004', 'type' => 'credit_card'],
    ]);
});

it('returns empty array when no credit cards found', function () {
    $detector = new CreditCard();
    $result = $detector->detect('No credit card here!');

    expect($result)->toBe([]);
});

it('does not match numbers that are too short', function () {
    $detector = new CreditCard();
    $result = $detector->detect('Short: 411111111111');

    expect($result)->toBe([]);
});
