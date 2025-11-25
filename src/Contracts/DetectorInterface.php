<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Contracts;

interface DetectorInterface
{
    /**
     * Detect sensitive data in the given text.
     *
     * @param string $text The text to scan for sensitive data
     * @return array<int, array{match: string, type: string}> Array of matches with their types
     */
    public function detect(string $text): array;
}
