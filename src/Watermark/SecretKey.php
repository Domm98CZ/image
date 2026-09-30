<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark;

use Domm98CZ\Image\Exception\InvalidWatermarkException;
use SensitiveParameter;

// Holds the watermark secret without ever exposing it (no getter, hidden from dumps, not serializable).
final readonly class SecretKey
{
    public const MIN_BYTES = 16;

    private string $key;

    public function __construct(#[SensitiveParameter] string $key)
    {
        if (strlen($key) < self::MIN_BYTES) {
            throw InvalidWatermarkException::keyTooShort(strlen($key), self::MIN_BYTES);
        }
        $this->key = $key;
    }

    // Domain separation: every use of the key (signing, embedding order...) gets its own derived value.
    public function derive(string $purpose, string $data = ''): string
    {
        return hash_hmac('sha256', $purpose . "\0" . $data, $this->key, true);
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['key' => '[redacted]'];
    }

    /** @return array<string, string> */
    public function __serialize(): array
    {
        // A caller bug (the exception is a LogicException subtype), not a runtime condition: secrets must not end up in caches or logs.
        throw InvalidWatermarkException::keyNotSerializable();
    }
}
