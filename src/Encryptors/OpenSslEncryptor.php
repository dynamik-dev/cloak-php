<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Encryptors;

use DynamikDev\Cloak\Contracts\EncryptorInterface;
use RuntimeException;

/**
 * OpenSSL-based encryptor using AES-256-GCM authenticated encryption.
 *
 * This encryptor provides strong encryption with authentication, ensuring both
 * confidentiality and integrity of the encrypted data. It uses a random IV
 * (Initialization Vector) for each encryption operation.
 */
class OpenSslEncryptor implements EncryptorInterface
{
    protected const CIPHER = 'aes-256-gcm';
    protected const KEY_LENGTH = 32; // 256 bits
    protected const IV_LENGTH = 12; // 96 bits (recommended for GCM)
    protected const TAG_LENGTH = 16; // 128 bits

    protected string $key;
    protected string $envKeyName;

    /**
     * @param string|null $key The encryption key (32 bytes raw or base64-encoded). If null, reads from env variable
     * @param string $envKeyName The environment variable name to read the key from (default: CLOAK_PRIVATE_KEY)
     * @throws RuntimeException If the key is invalid or not found
     */
    public function __construct(?string $key = null, string $envKeyName = 'CLOAK_PRIVATE_KEY')
    {
        $this->envKeyName = $envKeyName;
        $key ??= $this->getKeyFromEnvironment();
        $this->key = $this->prepareKey($key);
    }

    /**
     * Generate a secure encryption key.
     *
     * @return string Base64-encoded 32-byte key
     * @throws RuntimeException If random bytes generation fails
     */
    public static function generateKey(): string
    {
        try {
            return base64_encode(random_bytes(self::KEY_LENGTH));
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to generate encryption key: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Encrypt a value using AES-256-GCM.
     *
     * Format: base64(iv || ciphertext || tag)
     *
     * @param string $value The plaintext value to encrypt
     * @return string The encrypted value (base64-encoded)
     * @throws RuntimeException If encryption fails
     */
    public function encrypt(string $value): string
    {
        try {
            $iv = random_bytes(self::IV_LENGTH);
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to generate IV: ' . $e->getMessage(), 0, $e);
        }

        $tag = '';

        $ciphertext = openssl_encrypt(
            $value,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return base64_encode($iv . $ciphertext . $tag);
    }

    /**
     * Decrypt a previously encrypted value.
     *
     * @param string $encrypted The encrypted value (base64-encoded)
     * @return string The decrypted plaintext value
     * @throws RuntimeException If decryption fails or data is invalid
     */
    public function decrypt(string $encrypted): string
    {
        $data = base64_decode($encrypted, true);

        if ($data === false) {
            throw new RuntimeException('Decryption failed: invalid base64 encoding');
        }

        $dataLength = strlen($data);
        $minLength = self::IV_LENGTH + self::TAG_LENGTH;

        if ($dataLength < $minLength) {
            throw new RuntimeException('Decryption failed: encrypted data is too short');
        }

        $iv = substr($data, 0, self::IV_LENGTH);
        $tag = substr($data, -self::TAG_LENGTH);
        $ciphertext = substr($data, self::IV_LENGTH, -self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed: authentication tag mismatch or corrupted data');
        }

        return $plaintext;
    }

    /**
     * Get encryption key from environment variable.
     *
     * @return string The encryption key from the configured environment variable
     * @throws RuntimeException If the environment variable is not set
     */
    protected function getKeyFromEnvironment(): string
    {
        if (($key = getenv($this->envKeyName)) !== false && $key !== '') {
            return $key;
        }

        $envValue = $_ENV[$this->envKeyName] ?? null;

        if (is_string($envValue) && $envValue !== '') {
            return $envValue;
        }

        throw new RuntimeException(
            sprintf(
                'Encryption key not provided and %s environment variable is not set',
                $this->envKeyName
            )
        );
    }

    /**
     * Prepare and validate the encryption key.
     *
     * @param string $key The raw or base64-encoded key
     * @return string The raw 32-byte key
     * @throws RuntimeException If the key is invalid
     */
    protected function prepareKey(string $key): string
    {
        $decoded = base64_decode($key, true);

        if ($decoded !== false && strlen($decoded) === self::KEY_LENGTH) {
            return $decoded;
        }

        if (strlen($key) === self::KEY_LENGTH) {
            return $key;
        }

        throw new RuntimeException(
            sprintf(
                'Invalid encryption key: must be %d bytes raw or base64-encoded',
                self::KEY_LENGTH
            )
        );
    }
}
