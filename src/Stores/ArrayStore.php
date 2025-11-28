<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Stores;

use DynamikDev\Cloak\Contracts\StoreInterface;

/**
 * In-memory store for testing and single-request use cases.
 */
class ArrayStore implements StoreInterface
{
    /**
     * @var array<string, array<string, string>>
     */
    protected array $data = [];

    /**
     * @param array<string, string> $map
     */
    public function put(string $key, array $map): void
    {
        $this->data[$key] = $map;
    }

    /**
     * @return array<string, string>|null
     */
    public function get(string $key): ?array
    {
        return $this->data[$key] ?? null;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }
}
