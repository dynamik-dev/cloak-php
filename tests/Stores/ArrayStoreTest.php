<?php

declare(strict_types=1);

use DynamikDev\Cloak\Contracts\StoreInterface;
use DynamikDev\Cloak\Stores\ArrayStore;

it('implements StoreInterface', function () {
    $store = new ArrayStore();
    expect($store)->toBeInstanceOf(StoreInterface::class);
});

it('stores and retrieves a mapping', function () {
    $store = new ArrayStore();
    $map = ['{{EMAIL_abc123_1}}' => 'test@example.com'];

    $store->put('cloak:abc123', $map);

    expect($store->get('cloak:abc123'))->toBe($map);
});

it('returns null for missing key', function () {
    $store = new ArrayStore();

    expect($store->get('nonexistent'))->toBeNull();
});

it('forgets a stored mapping', function () {
    $store = new ArrayStore();
    $map = ['{{EMAIL_abc123_1}}' => 'test@example.com'];

    $store->put('cloak:abc123', $map);
    $store->forget('cloak:abc123');

    expect($store->get('cloak:abc123'))->toBeNull();
});

it('can store multiple mappings', function () {
    $store = new ArrayStore();
    $map1 = ['{{EMAIL_abc123_1}}' => 'test@example.com'];
    $map2 = ['{{PHONE_def456_1}}' => '555-123-4567'];

    $store->put('cloak:abc123', $map1);
    $store->put('cloak:def456', $map2);

    expect($store->get('cloak:abc123'))->toBe($map1);
    expect($store->get('cloak:def456'))->toBe($map2);
});

it('overwrites existing mapping with same key', function () {
    $store = new ArrayStore();
    $map1 = ['{{EMAIL_abc123_1}}' => 'old@example.com'];
    $map2 = ['{{EMAIL_abc123_1}}' => 'new@example.com'];

    $store->put('cloak:abc123', $map1);
    $store->put('cloak:abc123', $map2);

    expect($store->get('cloak:abc123'))->toBe($map2);
});
