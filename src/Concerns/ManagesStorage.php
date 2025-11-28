<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Concerns;

use DynamikDev\Cloak\Contracts\StoreInterface;
use DynamikDev\Cloak\Stores\ArrayStore;

trait ManagesStorage
{
    protected static ?StoreInterface $defaultStore = null;

    protected int $ttl = 3600;

    public function withTtl(int $ttl): self
    {
        $this->ttl = $ttl;

        return $this;
    }

    protected static function getDefaultStore(): StoreInterface
    {
        if (self::$defaultStore === null) {
            self::$defaultStore = new ArrayStore();
        }

        return self::$defaultStore;
    }
}
