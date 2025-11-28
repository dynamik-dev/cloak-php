<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Detectors;

use DynamikDev\Cloak\Contracts\DetectorInterface;

class Pattern implements DetectorInterface
{
    public function __construct(
        private readonly string $regex,
        private readonly string $type
    ) {
    }

    public function detect(string $text): array
    {
        preg_match_all($this->regex, $text, $matches);

        return array_map(
            fn (string $match): array => ['match' => $match, 'type' => $this->type],
            $matches[0]
        );
    }
}
