<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Detectors;

use DynamikDev\Cloak\Contracts\DetectorInterface;

/**
 * Detects email addresses in text.
 */
class Email implements DetectorInterface
{
    protected const PATTERN = '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/';

    /**
     * @return array<int, array{match: string, type: string}>
     */
    public function detect(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_map(
            fn (string $match): array => ['match' => $match, 'type' => 'email'],
            $matches[0]
        );
    }
}
