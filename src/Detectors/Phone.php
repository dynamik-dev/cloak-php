<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\Detectors;

use DynamikDev\Cloak\Contracts\DetectorInterface;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

/**
 * Detects phone numbers in text using Google's libphonenumber library.
 * Supports international formats and reduces false positives.
 */
class Phone implements DetectorInterface
{
    protected PhoneNumberUtil $phoneUtil;
    protected ?string $defaultRegion;

    /**
     * @param string|null $defaultRegion Two-letter country code (e.g., 'US', 'GB').
     *                                   Null attempts detection for all regions.
     */
    public function __construct(?string $defaultRegion = null)
    {
        $this->phoneUtil = PhoneNumberUtil::getInstance();
        $this->defaultRegion = $defaultRegion;
    }

    /**
     * @return array<int, array{match: string, type: string}>
     */
    public function detect(string $text): array
    {
        $results = [];

        try {
            $matcher = $this->phoneUtil->findNumbers($text, $this->defaultRegion);

            foreach ($matcher as $match) {
                if ($match !== null) {
                    $results[] = [
                        'match' => $match->rawString(),
                        'type' => 'phone',
                    ];
                }
            }
        } catch (NumberParseException $e) {
            return [];
        }

        return $results;
    }
}
