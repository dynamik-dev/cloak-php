# Cloak

A simple, extensible PHP package for redacting sensitive data from strings and revealing them later.

```php
use DynamikDev\Cloak\Cloak;

$cloak = Cloak::make();

$cloaked = $cloak->cloak('Email me at john@example.com');
// "Email me at {{EMAIL_x7k2m9_1}}"

$original = $cloak->uncloak($cloaked);
// "Email me at john@example.com"
```

## Installation

```bash
composer require dynamik-dev/cloak-php
```

## Requirements

- PHP 8.2+
- ext-mbstring (required by libphonenumber)

## Quick Start

### Basic Usage

```php
use DynamikDev\Cloak\Cloak;

$cloak = Cloak::make();

$text = 'Contact: john@example.com, Phone: 555-123-4567';
$cloaked = $cloak->cloak($text);
// "Contact: {{EMAIL_x7k2m9_1}}, Phone: {{PHONE_x7k2m9_1}}"

$original = $cloak->uncloak($cloaked);
// "Contact: john@example.com, Phone: 555-123-4567"
```

### Using Specific Detectors

```php
use DynamikDev\Cloak\Detector;

// Only detect emails
$cloaked = $cloak->cloak($text, [Detector::email()]);

// Multiple detectors
$cloaked = $cloak->cloak($text, [
    Detector::email(),
    Detector::phone('US'),
    Detector::ssn(),
]);
```

### Configuring with Builder Methods

```php
use DynamikDev\Cloak\Cloak;
use DynamikDev\Cloak\Detector;
use DynamikDev\Cloak\Encryptors\OpenSslEncryptor;

$cloak = Cloak::make()
    ->withDetectors([Detector::email()])
    ->withTtl(7200)
    ->encrypt(OpenSslEncryptor::generateKey());

$cloaked = $cloak->cloak('Sensitive: john@example.com');
```

## Built-in Detectors

Cloak provides several built-in detectors for common sensitive data types:

```php
use DynamikDev\Cloak\Detector;

Detector::email();           // Email addresses
Detector::phone('US');       // Phone numbers (specify region code)
Detector::ssn();             // Social Security Numbers (XXX-XX-XXXX)
Detector::creditCard();      // Credit card numbers
Detector::all();             // All built-in detectors (uses US for phone)
```

### Phone Number Detection

Phone detection uses [libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php) for robust international phone number validation with intelligent false positive prevention.

**Features:**

- International format support for all countries
- Validates actual phone numbers (not just digit patterns)
- Filters out order IDs, timestamps, serial numbers, etc.
- Handles various formats: `(212) 456-7890`, `212-456-7890`, `+44 117 496 0123`

**Examples:**

```php
// US numbers
Detector::phone('US')->detect('Call 212-456-7890');

// UK numbers
Detector::phone('GB')->detect('Ring 0117 496 0123');

// International format
Detector::phone()->detect('Call +44 117 496 0123');

// Filters false positives
Detector::phone('US')->detect('Order #123456789012'); // Not detected
```

## Custom Detectors

Cloak supports two approaches for custom detectors: **factory methods** (concise) and **direct instantiation** (explicit).

### Pattern-based Detector

```php
use DynamikDev\Cloak\Detector;
use DynamikDev\Cloak\Detectors\Pattern;

// Factory method (concise)
$detector = Detector::pattern('/\b[A-Z]{2}\d{6}\b/', 'passport');

// Direct instantiation (explicit)
$detector = new Pattern('/\b[A-Z]{2}\d{6}\b/', 'passport');

$cloaked = $cloak->cloak('Passport: AB123456', [$detector]);
// "Passport: {{PASSPORT_x7k2m9_1}}"
```

### Word-based Detector

```php
use DynamikDev\Cloak\Detector;
use DynamikDev\Cloak\Detectors\Words;

// Factory method
$detector = Detector::words(['password', 'secret'], 'sensitive');

// Direct instantiation
$detector = new Words(['password', 'secret'], 'sensitive');

$cloaked = $cloak->cloak('The password is secret123', [$detector]);
// "The {{SENSITIVE_x7k2m9_1}} is {{SENSITIVE_x7k2m9_2}}123"
```

### Callable Detector

```php
use DynamikDev\Cloak\Detector;
use DynamikDev\Cloak\Detectors\Callback;

// Factory method
$detector = Detector::using(function (string $text): array {
    $matches = [];
    if (preg_match_all('/\bAPI_KEY_\w+\b/', $text, $found)) {
        foreach ($found[0] as $match) {
            $matches[] = ['match' => $match, 'type' => 'api_key'];
        }
    }
    return $matches;
});

// Direct instantiation
$detector = new Callback(function (string $text): array {
    // ... same logic
});
```

### Mixed Approach

You can mix both patterns freely:

```php
use DynamikDev\Cloak\Detector;
use DynamikDev\Cloak\Detectors\{Email, Pattern, Words};

$cloak->cloak($text, [
    new Email(),                                    // Direct
    Detector::phone('US'),                          // Factory
    new Pattern('/\b[A-Z]{2}\d{6}\b/', 'passport'), // Direct
    Detector::words(['secret'], 'sensitive'),       // Factory
]);
```

### Implementing DetectorInterface

For full control, implement the `DetectorInterface`:

```php
use DynamikDev\Cloak\Contracts\DetectorInterface;

class IpAddressDetector implements DetectorInterface
{
    public function detect(string $text): array
    {
        $matches = [];
        $pattern = '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/';

        if (preg_match_all($pattern, $text, $found)) {
            foreach ($found[0] as $match) {
                $matches[] = ['match' => $match, 'type' => 'ip_address'];
            }
        }

        return $matches;
    }
}

$cloaked = $cloak->cloak('Server: 192.168.1.1', [new IpAddressDetector()]);
// "Server: {{IP_ADDRESS_x7k2m9_1}}"
```

## Advanced Features

### Filtering Detections

Use filters to exclude certain detections from being cloaked:

```php
// Exclude test emails
$cloak = Cloak::make()
    ->filter(fn ($detection) => !str_ends_with($detection['match'], '@test.local'));

$text = 'prod@company.com and test@test.local';
$cloaked = $cloak->cloak($text, [Detector::email()]);
// "{{EMAIL_x7k2m9_1}} and test@test.local"
```

### Multiple Filters

Filters are applied in sequence, and all must return `true` for a detection to be included:

```php
$cloak = Cloak::make()
    ->filter(fn ($d) => $d['type'] === 'email')
    ->filter(fn ($d) => !str_contains($d['match'], 'noreply'));
```

### Lifecycle Callbacks

Hook into the cloaking/uncloaking process:

```php
$cloak = Cloak::make()
    ->beforeCloak(function (string $text) {
        // Normalize whitespace before processing
        return preg_replace('/\s+/', ' ', $text);
    })
    ->afterCloak(function (string $original, string $cloaked) {
        // Log the cloaking operation
        logger()->info('Cloaked text', ['original_length' => strlen($original)]);
    })
    ->beforeUncloak(function (string $text) {
        // Validate before uncloaking
        return $text;
    })
    ->afterUncloak(function (string $text) {
        // Post-process after uncloaking
        return trim($text);
    });
```

### Encryption

Encrypt sensitive values at rest using the convenient `encrypt()` method:

```php
use DynamikDev\Cloak\Encryptors\OpenSslEncryptor;

$cloak = Cloak::make()
    ->encrypt(OpenSslEncryptor::generateKey());

$cloaked = $cloak->cloak('Secret: john@example.com', [Detector::email()]);
// Values are encrypted in storage, but placeholders remain the same
```

**Environment Variable Support:**

```php
// Reads from CLOAK_PRIVATE_KEY environment variable
$cloak = Cloak::make()->encrypt();

// Or specify a custom environment variable
$encryptor = new OpenSslEncryptor(null, 'MY_ENCRYPTION_KEY');
$cloak = Cloak::make()->withEncryptor($encryptor);
```

**Custom Encryptors:**

For full control, use `withEncryptor()` with a custom implementation:

```php
$customEncryptor = new MyEncryptor($key);
$cloak = Cloak::make()->withEncryptor($customEncryptor);
```

## Storage

### Default Store (ArrayStore)

By default, Cloak uses `ArrayStore` for in-memory storage. This is perfect for:

- Testing
- Single-request scenarios
- Simple use cases without persistence

```php
use DynamikDev\Cloak\Stores\ArrayStore;

$store = new ArrayStore();
$cloak = Cloak::using($store);
```

### Custom Store Implementation

For persistent storage across requests, implement `StoreInterface`:

```php
use DynamikDev\Cloak\Contracts\StoreInterface;

class RedisStore implements StoreInterface
{
    public function __construct(
        private Redis $redis,
        private int $ttl = 3600
    ) {}

    public function put(string $key, array $map): void
    {
        $this->redis->setex($key, $this->ttl, json_encode($map));
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

// Configure TTL via constructor
$cloak = Cloak::using(new RedisStore($redis, ttl: 7200));
```

### Laravel Cache Store Example

```php
use DynamikDev\Cloak\Contracts\StoreInterface;
use Illuminate\Support\Facades\Cache;

class LaravelCacheStore implements StoreInterface
{
    public function __construct(private int $ttl = 3600) {}

    public function put(string $key, array $map): void
    {
        Cache::put($key, $map, $this->ttl);
    }

    public function get(string $key): ?array
    {
        return Cache::get($key);
    }

    public function forget(string $key): void
    {
        Cache::forget($key);
    }
}
```

## Extending Cloak

Cloak follows a compositional architecture, making it easy to extend with custom implementations.

### Custom Placeholder Generator

Create custom placeholder formats by implementing `PlaceholderGeneratorInterface`:

```php
use DynamikDev\Cloak\Contracts\PlaceholderGeneratorInterface;
use Ramsey\Uuid\Uuid;

class UuidPlaceholderGenerator implements PlaceholderGeneratorInterface
{
    public function generate(array $detections): array
    {
        $key = Uuid::uuid4()->toString();
        $map = [];

        foreach ($detections as $detection) {
            $uuid = Uuid::uuid4()->toString();
            $placeholder = "[{$detection['type']}:{$uuid}]";
            $map[$placeholder] = $detection['match'];
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
        // Extract [TYPE:UUID] placeholders and group by key
        // Implementation details...
        return [];
    }
}

$cloak = Cloak::make()
    ->withPlaceholderGenerator(new UuidPlaceholderGenerator());
```

### Custom Encryptor

Implement `EncryptorInterface` for custom encryption strategies:

```php
use DynamikDev\Cloak\Contracts\EncryptorInterface;

class SodiumEncryptor implements EncryptorInterface
{
    public function __construct(private string $key) {}

    public function encrypt(string $value): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($value, $nonce, $this->key);

        return base64_encode($nonce . $encrypted);
    }

    public function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted);
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }
}

$cloak = Cloak::make()
    ->withEncryptor(new SodiumEncryptor($key));
```

## Placeholder Format

Placeholders follow the format `{{TYPE_KEY_INDEX}}`:

- `TYPE`: Uppercase detector type (EMAIL, PHONE, SSN, CREDIT_CARD)
- `KEY`: 6-character alphanumeric unique key
- `INDEX`: Integer counter per type, starting at 1

**Example:** `{{EMAIL_x7k2m9_1}}`

The default format can be customized by implementing a custom `PlaceholderGeneratorInterface`.

## API Reference

### Factory Methods

```php
Cloak::make(?StoreInterface $store = null): self
Cloak::using(StoreInterface $store): self
```

### Builder Methods

```php
->withDetectors(array $detectors): self
->filter(callable $callback): self
->withPlaceholderGenerator(PlaceholderGeneratorInterface $generator): self
->withEncryptor(EncryptorInterface $encryptor): self
->encrypt(?string $key = null): self  // Convenience method for OpenSslEncryptor
```

### Lifecycle Callbacks

```php
->beforeCloak(callable $callback): self
->afterCloak(callable $callback): self
->beforeUncloak(callable $callback): self
->afterUncloak(callable $callback): self
```

### Core Methods

```php
->cloak(string $text, ?array $detectors = null): string
->uncloak(string $text): string
```

## Edge Cases

- **Same value appears multiple times**: Reuses the same placeholder
- **No detections found**: Returns original text unchanged
- **Missing cache on uncloak**: Leaves placeholder in place
- **Empty input**: Returns empty string
- **Overlapping patterns**: All patterns are processed independently

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
