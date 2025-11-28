<?php

declare(strict_types=1);

namespace DynamikDev\Cloak;

use DynamikDev\Cloak\Concerns\HasLifecycleCallbacks;
use DynamikDev\Cloak\Concerns\ManagesStorage;
use DynamikDev\Cloak\Contracts\DetectorInterface;
use DynamikDev\Cloak\Contracts\EncryptorInterface;
use DynamikDev\Cloak\Contracts\PlaceholderGeneratorInterface;
use DynamikDev\Cloak\Contracts\StoreInterface;
use DynamikDev\Cloak\Encryptors\NullEncryptor;
use DynamikDev\Cloak\Encryptors\OpenSslEncryptor;
use DynamikDev\Cloak\PlaceholderGenerators\DefaultPlaceholderGenerator;

/**
 * Main class for cloaking and uncloaking sensitive data.
 */
class Cloak
{
    use HasLifecycleCallbacks;
    use ManagesStorage;

    /** @var (callable(): self)|null */
    protected static $resolver = null;

    /** @var array<int, DetectorInterface>|null */
    protected ?array $defaultDetectors = null;

    /** @var array<int, callable(array{match: string, type: string}): bool> */
    protected array $filters = [];

    protected function __construct(
        protected readonly StoreInterface $store,
        protected PlaceholderGeneratorInterface $placeholderGenerator = new DefaultPlaceholderGenerator(),
        protected EncryptorInterface $encryptor = new NullEncryptor()
    ) {
    }

    public static function using(StoreInterface $store): self
    {
        return new self($store);
    }

    public static function make(?StoreInterface $store = null): self
    {
        if (self::$resolver !== null) {
            return (self::$resolver)();
        }

        return new self($store ?? self::getDefaultStore());
    }

    /**
     * Set a custom resolver for creating Cloak instances.
     * This allows framework adapters to override the default factory behavior.
     *
     * @param callable(): self $resolver
     */
    public static function resolveUsing(callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Clear the custom resolver, reverting to default factory behavior.
     */
    public static function clearResolver(): void
    {
        self::$resolver = null;
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

    public function withPlaceholderGenerator(PlaceholderGeneratorInterface $generator): self
    {
        $this->placeholderGenerator = $generator;

        return $this;
    }

    public function withEncryptor(EncryptorInterface $encryptor): self
    {
        $this->encryptor = $encryptor;

        return $this;
    }

    public function encrypt(?string $key = null): self
    {
        $this->encryptor = new OpenSslEncryptor($key);

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

        $result = $this->placeholderGenerator->generate($detections);
        $key = $result['key'];
        $map = $result['map'];

        $this->store->put('cloak:' . $key, $this->encryptMap($map));

        $cloaked = $this->placeholderGenerator->replace($processedText, $map);

        $this->executeAfterCloakCallbacks($text, $cloaked);

        return $cloaked;
    }

    public function uncloak(string $text): string
    {
        $text = $this->executeBeforeUncloakCallbacks($text);

        $grouped = $this->placeholderGenerator->parse($text);

        if ($grouped === []) {
            return $text;
        }

        foreach ($grouped as $key => $placeholders) {
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

    /**
     * @param array<string, string> $map
     * @return array<string, string>
     */
    protected function encryptMap(array $map): array
    {
        $encrypted = [];

        foreach ($map as $placeholder => $value) {
            $encrypted[$placeholder] = $this->encryptor->encrypt($value);
        }

        return $encrypted;
    }

    /**
     * @param array<string, string> $map
     * @return array<string, string>
     */
    protected function decryptMap(array $map): array
    {
        $decrypted = [];

        foreach ($map as $placeholder => $value) {
            $decrypted[$placeholder] = $this->encryptor->decrypt($value);
        }

        return $decrypted;
    }
}
