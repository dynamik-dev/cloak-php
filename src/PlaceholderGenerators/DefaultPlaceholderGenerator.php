<?php

declare(strict_types=1);

namespace DynamikDev\Cloak\PlaceholderGenerators;

use DynamikDev\Cloak\Contracts\PlaceholderGeneratorInterface;

class DefaultPlaceholderGenerator implements PlaceholderGeneratorInterface
{
    protected const PLACEHOLDER_PATTERN = '/\{\{([A-Z_]+)_([a-zA-Z0-9]{6})_(\d+)\}\}/';
    protected const KEY_LENGTH = 6;
    protected const KEY_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function generate(array $detections): array
    {
        $key = $this->generateKey();
        $map = [];
        /** @var array<string, int> $typeCounters */
        $typeCounters = [];
        /** @var array<string, string> $valueToPlaceholder */
        $valueToPlaceholder = [];

        foreach ($detections as $detection) {
            if (isset($valueToPlaceholder[$detection['match']])) {
                continue;
            }

            $type = strtoupper($detection['type']);

            if (!isset($typeCounters[$type])) {
                $typeCounters[$type] = 0;
            }

            $typeCounters[$type]++;
            $placeholder = '{{' . $type . '_' . $key . '_' . $typeCounters[$type] . '}}';

            $map[$placeholder] = $detection['match'];
            $valueToPlaceholder[$detection['match']] = $placeholder;
        }

        return ['key' => $key, 'map' => $map];
    }

    public function replace(string $text, array $map): string
    {
        foreach ($map as $placeholder => $original) {
            $text = str_replace($original, $placeholder, $text);
        }

        return $text;
    }

    public function parse(string $text): array
    {
        preg_match_all(self::PLACEHOLDER_PATTERN, $text, $matches, PREG_SET_ORDER);

        $groups = [];

        foreach ($matches as $match) {
            if (!isset($groups[$match[2]])) {
                $groups[$match[2]] = [];
            }

            $groups[$match[2]][] = $match[0];
        }

        return $groups;
    }

    protected function generateKey(): string
    {
        $key = '';
        $charsLength = strlen(self::KEY_CHARS);

        for ($i = 0; $i < self::KEY_LENGTH; $i++) {
            $key .= self::KEY_CHARS[random_int(0, $charsLength - 1)];
        }

        return $key;
    }
}
