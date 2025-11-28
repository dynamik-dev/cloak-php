<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Encryptors;

use DynamikDev\Cloak\Contracts\EncryptorInterface;

/**
 * No-op encryptor that returns values unchanged.
 * Used as the default when no encryption is configured.
 */
class NullEncryptor implements EncryptorInterface
{
    public function encrypt(string $value): string
    {
        return $value;
    }

    public function decrypt(string $encrypted): string
    {
        return $encrypted;
    }
}
