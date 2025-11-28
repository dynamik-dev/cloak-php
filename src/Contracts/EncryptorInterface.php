<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Contracts;

/**
 * Interface for encrypting and decrypting sensitive values at rest.
 */
interface EncryptorInterface
{
    /**
     * Encrypt a value for storage.
     *
     * @param string $value The plaintext value to encrypt
     * @return string The encrypted value
     */
    public function encrypt(string $value): string;

    /**
     * Decrypt a previously encrypted value.
     *
     * @param string $encrypted The encrypted value
     * @return string The decrypted plaintext value
     * @throws \RuntimeException If decryption fails
     */
    public function decrypt(string $encrypted): string;
}
