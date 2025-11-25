<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Detectors;

use DynamikDev\Cloak\Contracts\DetectorInterface;

/**
 * Detects Social Security Numbers in text.
 */
class SSN implements DetectorInterface
{
    protected const PATTERN = '/\d{3}-\d{2}-\d{4}/';

    /**
     * @return array<int, array{match: string, type: string}>
     */
    public function detect(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_map(
            fn (string $match): array => ['match' => $match, 'type' => 'ssn'],
            $matches[0]
        );
    }
}
