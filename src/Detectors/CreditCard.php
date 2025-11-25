<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Detectors;

use DynamikDev\Cloak\Contracts\DetectorInterface;

/**
 * Detects credit card numbers in text.
 */
class CreditCard implements DetectorInterface
{
    protected const PATTERN = '/\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}/';

    /**
     * @return array<int, array{match: string, type: string}>
     */
    public function detect(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_map(
            fn (string $match): array => ['match' => $match, 'type' => 'credit_card'],
            $matches[0]
        );
    }
}
