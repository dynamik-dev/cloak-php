<?php

declare(strict_types=1);

namespace DynamikDev\Cloak;

use DynamikDev\Cloak\Detectors\Callback;
use DynamikDev\Cloak\Detectors\CreditCard;
use DynamikDev\Cloak\Detectors\Email;
use DynamikDev\Cloak\Detectors\Pattern;
use DynamikDev\Cloak\Detectors\Phone;
use DynamikDev\Cloak\Detectors\SSN;
use DynamikDev\Cloak\Detectors\Words;

/**
 * Factory class for creating detector instances.
 */
class Detector
{
    public static function email(): Email
    {
        return new Email();
    }

    /**
     * @param string|null $defaultRegion Two-letter country code (e.g., 'US', 'GB').
     *                                   Null attempts detection for all regions.
     */
    public static function phone(?string $defaultRegion = null): Phone
    {
        return new Phone($defaultRegion);
    }

    public static function ssn(): SSN
    {
        return new SSN();
    }

    public static function creditCard(): CreditCard
    {
        return new CreditCard();
    }

    /**
     * @return array<int, Email|Phone|SSN|CreditCard>
     */
    public static function all(): array
    {
        return [
            self::email(),
            self::phone('US'),
            self::ssn(),
            self::creditCard(),
        ];
    }

    public static function pattern(string $regex, string $type): Pattern
    {
        return new Pattern($regex, $type);
    }

    /**
     * @param array<int, string> $words
     */
    public static function words(array $words, string $type): Words
    {
        return new Words($words, $type);
    }

    /**
     * @param callable(string): array<int, array{match: string, type: string}> $callback
     */
    public static function using(callable $callback): Callback
    {
        return new Callback($callback);
    }
}
