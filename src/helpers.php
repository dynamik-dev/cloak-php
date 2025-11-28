<?php

declare(strict_types=1);

use DynamikDev\Cloak\Cloak;
use DynamikDev\Cloak\Contracts\StoreInterface;

if (! function_exists('cloak')) {
    /**
     * Cloak sensitive data in text using the provided detectors.
     *
     * @param string $text The text to cloak
     * @param array<int, \DynamikDev\Cloak\Contracts\DetectorInterface>|null $detectors Optional detectors to use
     * @param StoreInterface|null $store Optional store instance
     * @return string The cloaked text with placeholders
     */
    function cloak(string $text, ?array $detectors = null, ?StoreInterface $store = null): string
    {
        return Cloak::make($store)->cloak($text, $detectors);
    }
}

if (! function_exists('uncloak')) {
    /**
     * Uncloak text by replacing placeholders with original values.
     *
     * @param string $text The text containing placeholders
     * @param StoreInterface|null $store Optional store instance
     * @return string The uncloaked text with original values
     */
    function uncloak(string $text, ?StoreInterface $store = null): string
    {
        return Cloak::make($store)->uncloak($text);
    }
}
