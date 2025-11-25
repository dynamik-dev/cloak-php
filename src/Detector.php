<?php

declare(strict_types=1);

namespace DynamikDev\Cloak;

use DynamikDev\Cloak\Contracts\DetectorInterface;
use DynamikDev\Cloak\Detectors\CreditCard;
use DynamikDev\Cloak\Detectors\Email;
use DynamikDev\Cloak\Detectors\Phone;
use DynamikDev\Cloak\Detectors\SSN;

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
     * @return array<int, DetectorInterface>
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

    public static function pattern(string $regex, string $type): DetectorInterface
    {
        return new class ($regex, $type) implements DetectorInterface {
            public function __construct(
                private readonly string $regex,
                private readonly string $type
            ) {
            }

            /**
             * @return array<int, array{match: string, type: string}>
             */
            public function detect(string $text): array
            {
                preg_match_all($this->regex, $text, $matches);

                return array_map(
                    fn (string $match): array => ['match' => $match, 'type' => $this->type],
                    $matches[0]
                );
            }
        };
    }

    /**
     * @param array<int, string> $words
     */
    public static function words(array $words, string $type): DetectorInterface
    {
        return new class ($words, $type) implements DetectorInterface {
            /**
             * @param array<int, string> $words
             */
            public function __construct(
                private readonly array $words,
                private readonly string $type
            ) {
            }

            /**
             * @return array<int, array{match: string, type: string}>
             */
            public function detect(string $text): array
            {
                $matches = [];
                $lowerText = strtolower($text);

                foreach ($this->words as $word) {
                    $lowerWord = strtolower($word);
                    $pos = strpos($lowerText, $lowerWord);
                    if ($pos !== false) {
                        $matches[] = [
                            'match' => substr($text, $pos, strlen($word)),
                            'type' => $this->type,
                        ];
                    }
                }

                return $matches;
            }
        };
    }

    /**
     * @param callable(string): array<int, array{match: string, type: string}> $callback
     */
    public static function using(callable $callback): DetectorInterface
    {
        return new class ($callback) implements DetectorInterface {
            /**
             * @param callable(string): array<int, array{match: string, type: string}> $callback
             */
            public function __construct(
                private readonly mixed $callback
            ) {
            }

            /**
             * @return array<int, array{match: string, type: string}>
             */
            public function detect(string $text): array
            {
                return ($this->callback)($text);
            }
        };
    }
}
