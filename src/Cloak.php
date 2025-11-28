<?php

declare(strict_types=1);

namespace DynamikDev\Cloak;

use DynamikDev\Cloak\Concerns\HasLifecycleCallbacks;
use DynamikDev\Cloak\Contracts\DetectorInterface;
use DynamikDev\Cloak\Contracts\EncryptorInterface;
use DynamikDev\Cloak\Contracts\StoreInterface;
use DynamikDev\Cloak\Encryptors\NullEncryptor;
use DynamikDev\Cloak\Encryptors\OpenSslEncryptor;
use DynamikDev\Cloak\Stores\ArrayStore;

/**
 * Main class for cloaking and uncloaking sensitive data.
 */
class Cloak
{
    use HasLifecycleCallbacks;

    protected const PLACEHOLDER_PATTERN = '/\{\{([A-Z_]+)_([a-zA-Z0-9]{6})_(\d+)\}\}/';
    protected const KEY_LENGTH = 6;
    protected const KEY_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    protected static ?StoreInterface $defaultStore = null;

    /** @var array<int, DetectorInterface>|null */
    protected ?array $defaultDetectors = null;

    protected int $ttl = 3600;

    /** @var array<int, callable(array{match: string, type: string}): bool> */
    protected array $filters = [];

    protected ?EncryptorInterface $encryptor = null;

    /** @var callable|null */
    protected $encryptorCallback = null;

    protected function __construct(
        protected readonly StoreInterface $store
    ) {
    }

    public static function using(StoreInterface $store): self
    {
        return new self($store);
    }

    public static function make(?StoreInterface $store = null): self
    {
        return new self($store ?? self::getDefaultStore());
    }

    protected static function getDefaultStore(): StoreInterface
    {
        if (self::$defaultStore === null) {
            self::$defaultStore = new ArrayStore();
        }

        return self::$defaultStore;
    }

    /**
     * Set the default detectors to use when none are specified.
     *
     * @param array<int, DetectorInterface> $detectors
     * @return $this
     */
    public function withDetectors(array $detectors): self
    {
        $this->defaultDetectors = $detectors;

        return $this;
    }

    /**
     * Set the TTL (time to live) for stored mappings in seconds.
     *
     * @param int $ttl Time to live in seconds
     * @return $this
     */
    public function withTtl(int $ttl): self
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * Add a filter to exclude certain detections.
     * Multiple filters can be added - all must return true for a detection to be included.
     *
     * @param callable(array{match: string, type: string}): bool $callback Return false to exclude the detection
     * @return $this
     */
    public function filter(callable $callback): self
    {
        $this->filters[] = $callback;

        return $this;
    }

    /**
     * Enable encryption using the default OpenSslEncryptor.
     * If no key is provided, it will attempt to read from CLOAK_PRIVATE_KEY environment variable.
     *
     * @param string|null $key The encryption key (32 bytes raw or base64-encoded)
     * @return $this
     * @throws \RuntimeException If the key is invalid or not found
     */
    public function encrypt(?string $key = null): self
    {
        $this->encryptor = new OpenSslEncryptor($key);
        $this->encryptorCallback = null;

        return $this;
    }

    /**
     * Set a custom encryptor instance or callback.
     *
     * @param EncryptorInterface|callable(): EncryptorInterface $encryptor
     * @return $this
     */
    public function encryptUsing(EncryptorInterface|callable $encryptor): self
    {
        if (is_callable($encryptor)) {
            $this->encryptorCallback = $encryptor;
            $this->encryptor = null;
        } else {
            $this->encryptor = $encryptor;
            $this->encryptorCallback = null;
        }

        return $this;
    }

    /**
     * @param array<int, DetectorInterface>|null $detectors
     */
    public function cloak(string $text, ?array $detectors = null): string
    {
        $processedText = $this->executeBeforeCloakCallbacks($text);

        $detections = $this->applyFilters(
            $this->runDetectors($processedText, $detectors ?? $this->defaultDetectors ?? Detector::all())
        );

        if ($detections === []) {
            $this->executeAfterCloakCallbacks($text, $processedText);

            return $processedText;
        }

        $key = $this->generateKey();
        $map = $this->buildPlaceholderMap($detections, $key);

        $this->store->put('cloak:' . $key, $this->encryptMap($map), $this->ttl);

        $result = $this->replaceWithPlaceholders($processedText, $map);

        $this->executeAfterCloakCallbacks($text, $result);

        return $result;
    }

    public function uncloak(string $text): string
    {
        $text = $this->executeBeforeUncloakCallbacks($text);

        preg_match_all(self::PLACEHOLDER_PATTERN, $text, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return $text;
        }

        foreach ($this->groupPlaceholdersByKey($matches) as $key => $placeholders) {
            $encryptedMap = $this->store->get('cloak:' . $key);

            if ($encryptedMap === null) {
                continue;
            }

            foreach ($this->decryptMap($encryptedMap) as $placeholder => $value) {
                if (in_array($placeholder, $placeholders, true)) {
                    $text = str_replace($placeholder, $value, $text);
                }
            }
        }

        return $this->executeAfterUncloakCallbacks($text);
    }

    /**
     * @param array<int, DetectorInterface> $detectors
     * @return array<int, array{match: string, type: string}>
     */
    protected function runDetectors(string $text, array $detectors): array
    {
        $detections = [];

        foreach ($detectors as $detector) {
            foreach ($detector->detect($text) as $result) {
                $detections[] = $result;
            }
        }

        return $detections;
    }

    /**
     * Apply all registered filters to the detections.
     * All filters must return true for a detection to be included.
     *
     * @param array<int, array{match: string, type: string}> $detections
     * @return array<int, array{match: string, type: string}>
     */
    protected function applyFilters(array $detections): array
    {
        if ($this->filters === []) {
            return $detections;
        }

        foreach ($this->filters as $filter) {
            $detections = array_filter($detections, $filter);
        }

        return array_values($detections);
    }

    protected function generateKey(): string
    {
        $key = '';
        $charsLength = strlen(self::KEY_CHARS);

        for ($i = 0; $i < self::KEY_LENGTH; $i++) {
            $key .= self::KEY_CHARS[random_int(0, $charsLength - 1)];
        }

        return $key;
    }

    /**
     * @param array<int, array{match: string, type: string}> $detections
     * @return array<string, string> Placeholder to original value mapping
     */
    protected function buildPlaceholderMap(array $detections, string $key): array
    {
        $map = [];
        /** @var array<string, int> $typeCounters */
        $typeCounters = [];
        /** @var array<string, string> $valueToPlaceholder */
        $valueToPlaceholder = [];

        foreach ($detections as $detection) {
            if (isset($valueToPlaceholder[$detection['match']])) {
                continue;
            }

            $type = strtoupper($detection['type']);

            if (!isset($typeCounters[$type])) {
                $typeCounters[$type] = 0;
            }

            $typeCounters[$type]++;
            $placeholder = '{{' . $type . '_' . $key . '_' . $typeCounters[$type] . '}}';

            $map[$placeholder] = $detection['match'];
            $valueToPlaceholder[$detection['match']] = $placeholder;
        }

        return $map;
    }

    /**
     * @param array<string, string> $map Placeholder to original value
     */
    protected function replaceWithPlaceholders(string $text, array $map): string
    {
        foreach ($map as $placeholder => $original) {
            $text = str_replace($original, $placeholder, $text);
        }

        return $text;
    }

    /**
     * @param array<int, array<int, string>> $matches
     * @return array<string, array<int, string>>
     */
    protected function groupPlaceholdersByKey(array $matches): array
    {
        $groups = [];

        foreach ($matches as $match) {
            if (!isset($groups[$match[2]])) {
                $groups[$match[2]] = [];
            }

            $groups[$match[2]][] = $match[0];
        }

        return $groups;
    }

    /**
     * Get the encryptor instance, initializing from callback if needed.
     */
    protected function getEncryptor(): EncryptorInterface
    {
        if ($this->encryptorCallback !== null && $this->encryptor === null) {
            $result = ($this->encryptorCallback)();
            assert($result instanceof EncryptorInterface);
            $this->encryptor = $result;
            $this->encryptorCallback = null;
        }

        return $this->encryptor ?? new NullEncryptor();
    }

    /**
     * Encrypt the values in the placeholder map.
     *
     * @param array<string, string> $map
     * @return array<string, string>
     */
    protected function encryptMap(array $map): array
    {
        $encrypted = [];

        foreach ($map as $placeholder => $value) {
            $encrypted[$placeholder] = $this->getEncryptor()->encrypt($value);
        }

        return $encrypted;
    }

    /**
     * Decrypt the values in the placeholder map.
     *
     * @param array<string, string> $map
     * @return array<string, string>
     */
    protected function decryptMap(array $map): array
    {
        $decrypted = [];

        foreach ($map as $placeholder => $value) {
            $decrypted[$placeholder] = $this->getEncryptor()->decrypt($value);
        }

        return $decrypted;
    }
}
