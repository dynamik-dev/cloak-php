<?php

declare(strict_types=1);

use DynamikDev\Cloak\Cloak;
use DynamikDev\Cloak\Detector;
use DynamikDev\Cloak\Stores\ArrayStore;

describe('full cloak/uncloak cycle', function () {
    it('cloaks and uncloaks single email', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);
        $original = 'Contact us at support@company.com for help';

        $cloaked = $cloak->cloak($original, [Detector::email()]);
        $uncloaked = $cloak->uncloak($cloaked);

        expect($cloaked)->not->toBe($original);
        expect($cloaked)->toContain('{{EMAIL_');
        expect($uncloaked)->toBe($original);
    });

    it('cloaks and uncloaks all sensitive data types', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);
        $original = 'Email: john@example.com, Phone: 212-456-7890, SSN: 123-45-6789, Card: 4111111111111111';

        $cloaked = $cloak->cloak($original);
        $uncloaked = $cloak->uncloak($cloaked);

        expect($cloaked)->toContain('{{EMAIL_');
        expect($cloaked)->toContain('{{PHONE_');
        expect($cloaked)->toContain('{{SSN_');
        expect($cloaked)->toContain('{{CREDIT_CARD_');
        expect($uncloaked)->toBe($original);
    });

    it('handles multiple cloaks with different keys', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);

        $text1 = 'Email: a@b.com';
        $text2 = 'Email: c@d.com';

        $cloaked1 = $cloak->cloak($text1, [Detector::email()]);
        $cloaked2 = $cloak->cloak($text2, [Detector::email()]);

        // Extract keys from both cloaked texts
        preg_match('/\{\{EMAIL_([a-zA-Z0-9]{6})_1\}\}/', $cloaked1, $match1);
        preg_match('/\{\{EMAIL_([a-zA-Z0-9]{6})_1\}\}/', $cloaked2, $match2);

        // Keys should be different
        expect($match1[1])->not->toBe($match2[1]);

        // Both should uncloak correctly
        expect($cloak->uncloak($cloaked1))->toBe($text1);
        expect($cloak->uncloak($cloaked2))->toBe($text2);
    });

    it('reveals multiple keys in same string', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);

        $text1 = 'Email: first@example.com';
        $text2 = 'Phone: 555-111-2222';

        $cloaked1 = $cloak->cloak($text1, [Detector::email()]);
        $cloaked2 = $cloak->cloak($text2, [Detector::phone()]);

        // Combine cloaked texts
        $combined = $cloaked1 . ' and ' . $cloaked2;

        // Should reveal both
        $revealed = $cloak->uncloak($combined);

        expect($revealed)->toBe($text1 . ' and ' . $text2);
    });
});

describe('custom detectors', function () {
    it('works with pattern detector', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);
        $passportDetector = Detector::pattern('/\b[A-Z]{2}\d{6}\b/', 'passport');

        $original = 'Passport number: AB123456';
        $cloaked = $cloak->cloak($original, [$passportDetector]);

        expect($cloaked)->toContain('{{PASSPORT_');
        expect($cloak->uncloak($cloaked))->toBe($original);
    });

    it('works with words detector', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);
        $sensitiveDetector = Detector::words(['password', 'secret'], 'sensitive');

        $original = 'The password is secret123';
        $cloaked = $cloak->cloak($original, [$sensitiveDetector]);

        expect($cloaked)->toContain('{{SENSITIVE_');
        expect($cloak->uncloak($cloaked))->toBe($original);
    });

    it('works with callable detector', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);
        $customDetector = Detector::using(function (string $text): array {
            $matches = [];
            if (preg_match_all('/\bAPI_KEY_\w+\b/', $text, $found)) {
                foreach ($found[0] as $match) {
                    $matches[] = ['match' => $match, 'type' => 'api_key'];
                }
            }
            return $matches;
        });

        $original = 'Use API_KEY_abc123 for authentication';
        $cloaked = $cloak->cloak($original, [$customDetector]);

        expect($cloaked)->toContain('{{API_KEY_');
        expect($cloak->uncloak($cloaked))->toBe($original);
    });
});

describe('edge cases', function () {
    it('handles same value appearing multiple times', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);

        $original = 'Primary: test@example.com, Backup: test@example.com';
        $cloaked = $cloak->cloak($original, [Detector::email()]);

        // Count placeholders - should be the same placeholder twice
        preg_match_all('/\{\{EMAIL_[a-zA-Z0-9]{6}_1\}\}/', $cloaked, $matches);
        expect($matches[0])->toHaveCount(2);
        expect($matches[0][0])->toBe($matches[0][1]);

        expect($cloak->uncloak($cloaked))->toBe($original);
    });

    it('handles overlapping patterns gracefully', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);

        // Phone and credit card are both present
        $original = 'Phone: 2124567890 Card: 4111111111111111';
        $cloaked = $cloak->cloak($original, [Detector::phone('US'), Detector::creditCard()]);

        // Should find both patterns
        expect($cloaked)->toContain('{{PHONE_');
        expect($cloaked)->toContain('{{CREDIT_CARD_');

        $uncloaked = $cloak->uncloak($cloaked);
        // After uncloaking, we should get the original back
        expect($uncloaked)->toBe($original);
    });

    it('preserves special characters in surrounding text', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);

        $original = 'Email: <test@example.com> (primary) [confirmed]';
        $cloaked = $cloak->cloak($original, [Detector::email()]);

        expect($cloaked)->toContain('Email: <{{EMAIL_');
        expect($cloaked)->toContain('}}> (primary) [confirmed]');

        expect($cloak->uncloak($cloaked))->toBe($original);
    });

    it('handles empty string', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);

        expect($cloak->cloak('', [Detector::email()]))->toBe('');
        expect($cloak->uncloak(''))->toBe('');
    });

    it('handles unicode text', function () {
        $store = new ArrayStore();
        $cloak = Cloak::using($store);

        $original = 'Contáctenos: test@example.com 日本語';
        $cloaked = $cloak->cloak($original, [Detector::email()]);

        expect($cloak->uncloak($cloaked))->toBe($original);
    });
});
