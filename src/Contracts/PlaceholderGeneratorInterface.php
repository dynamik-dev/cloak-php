<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Contracts;

interface PlaceholderGeneratorInterface
{
    /**
     * Generate a placeholder map from detections.
     *
     * @param array<int, array{match: string, type: string}> $detections
     * @return array{key: string, map: array<string, string>} Key and placeholder mapping
     */
    public function generate(array $detections): array;

    /**
     * Replace original values with placeholders in text.
     *
     * @param string $text The text to process
     * @param array<string, string> $map Placeholder to original value mapping
     * @return string Text with placeholders
     */
    public function replace(string $text, array $map): string;

    /**
     * Parse placeholders from text and group them by key.
     *
     * @param string $text The text containing placeholders
     * @return array<string, array<int, string>> Grouped placeholders by key
     */
    public function parse(string $text): array;
}
