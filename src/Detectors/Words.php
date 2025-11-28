<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Detectors;

use DynamikDev\Cloak\Contracts\DetectorInterface;

class Words implements DetectorInterface
{
    /**
     * @param array<int, string> $words
     */
    public function __construct(
        private readonly array $words,
        private readonly string $type
    ) {
    }

    public function detect(string $text): array
    {
        $matches = [];
        $lowerText = strtolower($text);

        foreach ($this->words as $word) {
            if (($pos = strpos($lowerText, strtolower($word))) !== false) {
                $matches[] = [
                    'match' => substr($text, $pos, strlen($word)),
                    'type' => $this->type,
                ];
            }
        }

        return $matches;
    }
}
