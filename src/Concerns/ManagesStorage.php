<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Concerns;

use DynamikDev\Cloak\Contracts\StoreInterface;
use DynamikDev\Cloak\Stores\ArrayStore;

trait ManagesStorage
{
    protected static ?StoreInterface $defaultStore = null;

    protected static function getDefaultStore(): StoreInterface
    {
        if (self::$defaultStore === null) {
            self::$defaultStore = new ArrayStore();
        }

        return self::$defaultStore;
    }
}
