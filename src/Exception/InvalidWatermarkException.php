<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Format\FormatName;

final class InvalidWatermarkException extends InvalidArgumentException
{
    public static function payloadTooLarge(int $bytes, int $max): self
    {
        return new self(sprintf('Watermark payload is %d bytes; this method carries at most %d.', $bytes, $max));
    }

    public static function textTooLong(int $length, int $max): self
    {
        return new self(sprintf('Watermark text must be valid UTF-8 of at most %d characters, got %d bytes.', $max, $length));
    }

    public static function encodedWatermarkNeedsEncoding(): self
    {
        return new self('A metadata watermark is applied to the encoded file: call encode() or save(), not toImage().');
    }

    public static function unknownKind(string $class): self
    {
        return new self(sprintf('Watermark %s must implement PixelWatermarkInterface or EncodedWatermarkInterface.', $class));
    }

    public static function emptyPayload(): self
    {
        return new self('Watermark payload must not be empty.');
    }

    public static function keyTooShort(int $bytes, int $min): self
    {
        return new self(sprintf('Watermark key must be at least %d bytes, got %d.', $min, $bytes));
    }

    public static function keyNotSerializable(): self
    {
        return new self('SecretKey cannot be serialized.');
    }

    public static function insufficientCapacity(string $method, int $neededBits, int $availableBits): self
    {
        return new self(sprintf('%s watermark needs %d bits of capacity, the image offers %d.', $method, $neededBits, $availableBits));
    }

    public static function lostByOutput(string $method, FormatName $format): self
    {
        return new self(sprintf('A %s watermark does not survive lossy %s output; use PNG or lossless WebP, or a DCT watermark.', $method, $format->label()));
    }
}
