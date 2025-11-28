<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Detectors;

use DynamikDev\Cloak\Contracts\DetectorInterface;

class Callback implements DetectorInterface
{
    /**
     * @param callable(string): array<int, array{match: string, type: string}> $callback
     */
    public function __construct(
        private readonly mixed $callback
    ) {
    }

    public function detect(string $text): array
    {
        return ($this->callback)($text);
    }
}
