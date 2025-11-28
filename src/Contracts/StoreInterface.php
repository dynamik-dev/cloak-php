<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Contracts;

interface StoreInterface
{
    /**
     * Store a mapping of placeholders to original values.
     *
     * @param string $key The unique storage key
     * @param array<string, string> $map Placeholder to original value mapping
     */
    public function put(string $key, array $map): void;

    /**
     * Retrieve a mapping by key.
     *
     * @param string $key The storage key
     * @return array<string, string>|null The mapping or null if not found
     */
    public function get(string $key): ?array;

    /**
     * Remove a mapping by key.
     *
     * @param string $key The storage key
     */
    public function forget(string $key): void;
}
