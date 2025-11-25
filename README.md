# Cloak

A PHP package that redacts sensitive data from strings and reveals them later using placeholder tokens with cached key-value storage.

## Installation

```bash
composer require dynamikdev/cloak-php
```

## Requirements

- PHP 8.2+
- ext-mbstring (required by libphonenumber)

## Dependencies

- [giggsey/libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php) - Industry-standard phone number validation and detection

## Usage

### Basic Usage

```php
use DynamikDev\Cloak\Cloak;

// Uses ArrayStore by default (in-memory storage)
$cloak = Cloak::make();

// Cloak sensitive data
$text = 'Contact me at john@example.com or 555-123-4567';
$cloaked = $cloak->cloak($text);
// "Contact me at {{EMAIL_x7k2m9_1}} or {{PHONE_x7k2m9_1}}"

// Uncloak to reveal original data
$original = $cloak->uncloak($cloaked);
// "Contact me at john@example.com or 555-123-4567"
```

### Using a Custom Store

```php
use DynamikDev\Cloak\Cloak;
use DynamikDev\Cloak\Stores\ArrayStore;

// Explicitly provide a store instance
$store = new ArrayStore();
$cloak = Cloak::using($store);

// Or pass to make()
$cloak = Cloak::make($store);
```

### Placeholder Format

Placeholders follow the format `{{TYPE_KEY_INDEX}}`:

- `TYPE`: Uppercase type (EMAIL, PHONE, SSN, CREDIT_CARD)
- `KEY`: 6-character alphanumeric unique key
- `INDEX`: Integer counter per type, starting at 1

Example: `{{EMAIL_x7k2m9_1}}`

### Built-in Detectors

```php
use DynamikDev\Cloak\Detector;

Detector::email();           // Detects email addresses
Detector::phone();           // Detects phone numbers (defaults to US region)
Detector::phone('GB');       // Detects phone numbers for specific region (e.g., UK)
Detector::ssn();             // Detects SSN (XXX-XX-XXXX)
Detector::creditCard();      // Detects 16-digit credit card numbers
Detector::all();             // Returns array of all built-in detectors (uses US for phone)
```

#### Phone Number Detection

Phone detection uses [libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php) (Google's libphonenumber) for robust international phone number detection with **intelligent false positive prevention**.

**Key Features:**
- Supports international formats from all countries
- Validates actual phone numbers (not just patterns)
- Filters out false positives like order IDs, timestamps, and serial numbers
- Handles various formats: `(212) 456-7890`, `212-456-7890`, `212.456.7890`, `2124567890`

**Examples:**
```php
// Detects US numbers
$detector = Detector::phone('US');
$result = $detector->detect('Call 212-456-7890'); // ✓ Detected

// Detects UK numbers
$detector = Detector::phone('GB');
$result = $detector->detect('Ring 0117 496 0123'); // ✓ Detected

// International format
$detector = Detector::phone();
$result = $detector->detect('Call +44 117 496 0123'); // ✓ Detected

// Filters false positives
$detector = Detector::phone('US');
$result = $detector->detect('Order #123456789012'); // ✗ Not detected
$result = $detector->detect('Timestamp: 20231225123456'); // ✗ Not detected
```

### Using Specific Detectors

```php
// Only detect emails
$cloaked = $cloak->cloak($text, [Detector::email()]);

// Detect emails and US phone numbers
$cloaked = $cloak->cloak($text, [
    Detector::email(),
    Detector::phone('US'),
]);

// Detect UK phone numbers
$cloaked = $cloak->cloak($text, [Detector::phone('GB')]);
```

### Custom Detectors

#### Pattern-based Detector

```php
use DynamikDev\Cloak\Detector;

// Detect passport numbers
$passportDetector = Detector::pattern('/\b[A-Z]{2}\d{6}\b/', 'passport');

$cloaked = $cloak->cloak('Passport: AB123456', [$passportDetector]);
// "Passport: {{PASSPORT_x7k2m9_1}}"
```

#### Word-based Detector

```php
use DynamikDev\Cloak\Detector;

// Detect specific sensitive words (case-insensitive)
$sensitiveDetector = Detector::words(['password', 'secret'], 'sensitive');

$cloaked = $cloak->cloak('The password is secret123', [$sensitiveDetector]);
// "The {{SENSITIVE_x7k2m9_1}} is {{SENSITIVE_x7k2m9_2}}123"
```

#### Callable Detector

```php
use DynamikDev\Cloak\Detector;

// Custom detection logic
$apiKeyDetector = Detector::using(function (string $text): array {
    $matches = [];
    if (preg_match_all('/\bAPI_KEY_\w+\b/', $text, $found)) {
        foreach ($found[0] as $match) {
            $matches[] = ['match' => $match, 'type' => 'api_key'];
        }
    }
    return $matches;
});

$cloaked = $cloak->cloak('Use API_KEY_abc123', [$apiKeyDetector]);
// "Use {{API_KEY_x7k2m9_1}}"
```

### Storage

#### Default Store (ArrayStore)

Cloak uses `ArrayStore` by default, which provides in-memory storage. This is ideal for:
- Testing
- Single-request scenarios
- Simple use cases without persistence needs

#### Custom Store Implementation

For persistent storage across requests, implement `StoreInterface`:

```php
use DynamikDev\Cloak\Contracts\StoreInterface;

class RedisStore implements StoreInterface
{
    public function __construct(private Redis $redis) {}

    public function put(string $key, array $map, int $ttl = 3600): void
    {
        $this->redis->setex($key, $ttl, json_encode($map));
    }

    public function get(string $key): ?array
    {
        $data = $this->redis->get($key);
        return $data ? json_decode($data, true) : null;
    }

    public function forget(string $key): void
    {
        $this->redis->del($key);
    }
}

$store = new RedisStore($redis);
$cloak = Cloak::using($store);
```

## Edge Cases

- **Same value multiple times**: Reuses the same placeholder
- **No detections**: Returns original text unchanged
- **Missing cache on uncloak**: Leaves placeholder in place
- **Empty input**: Returns empty string

## Testing

```bash
./vendor/bin/pest
```

## Static Analysis

```bash
./vendor/bin/phpstan analyse
```

## License

MIT
