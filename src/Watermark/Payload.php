<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark;

use Domm98CZ\Image\Exception\InvalidWatermarkException;

final readonly class Payload
{
    public function __construct(
        public string $bytes,
    ) {
        if ($bytes === '') {
            throw InvalidWatermarkException::emptyPayload();
        }
    }

    public static function text(string $text): self
    {
        return new self($text);
    }

    public function length(): int
    {
        return strlen($this->bytes);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->bytes, $other->bytes);
    }
}
