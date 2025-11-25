<?php

declare(strict_types=1);

namespace DynamikDev\Cloak;

use DynamikDev\Cloak\Contracts\DetectorInterface;
use DynamikDev\Cloak\Contracts\StoreInterface;
use DynamikDev\Cloak\Stores\ArrayStore;

/**
 * Main class for cloaking and uncloaking sensitive data.
 */
class Cloak
{
    protected const PLACEHOLDER_PATTERN = '/\{\{([A-Z_]+)_([a-zA-Z0-9]{6})_(\d+)\}\}/';
    protected const KEY_LENGTH = 6;
    protected const KEY_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    protected static ?StoreInterface $defaultStore = null;

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
     * @param array<int, DetectorInterface>|null $detectors
     */
    public function cloak(string $text, ?array $detectors = null): string
    {
        $detectors ??= Detector::all();

        // Collect all detections
        $detections = $this->runDetectors($text, $detectors);

        if ($detections === []) {
            return $text;
        }

        // Generate unique key for this cloak operation
        $key = $this->generateKey();

        // Build placeholder map
        $map = $this->buildPlaceholderMap($detections, $key);

        // Store the mapping
        $this->store->put('cloak:' . $key, $map);

        // Replace original values with placeholders
        return $this->replaceWithPlaceholders($text, $map);
    }

    public function uncloak(string $text): string
    {
        // Find all placeholders in the text
        preg_match_all(self::PLACEHOLDER_PATTERN, $text, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return $text;
        }

        // Group placeholders by key
        $keyGroups = $this->groupPlaceholdersByKey($matches);

        // Fetch mappings and replace
        foreach ($keyGroups as $key => $placeholders) {
            $map = $this->store->get('cloak:' . $key);

            if ($map === null) {
                continue;
            }

            foreach ($placeholders as $placeholder) {
                if (isset($map[$placeholder])) {
                    $text = str_replace($placeholder, $map[$placeholder], $text);
                }
            }
        }

        return $text;
    }

    /**
     * @param array<int, DetectorInterface> $detectors
     * @return array<int, array{match: string, type: string}>
     */
    protected function runDetectors(string $text, array $detectors): array
    {
        $detections = [];

        foreach ($detectors as $detector) {
            $results = $detector->detect($text);
            foreach ($results as $result) {
                $detections[] = $result;
            }
        }

        return $detections;
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
            $match = $detection['match'];
            $type = strtoupper($detection['type']);

            // Reuse placeholder for same value
            if (isset($valueToPlaceholder[$match])) {
                continue;
            }

            // Initialize counter for this type
            if (!isset($typeCounters[$type])) {
                $typeCounters[$type] = 0;
            }

            $typeCounters[$type]++;
            $placeholder = '{{' . $type . '_' . $key . '_' . $typeCounters[$type] . '}}';

            $map[$placeholder] = $match;
            $valueToPlaceholder[$match] = $placeholder;
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
            $placeholder = $match[0];
            $key = $match[2];

            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }

            $groups[$key][] = $placeholder;
        }

        return $groups;
    }
}
