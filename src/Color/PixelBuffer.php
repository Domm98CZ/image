<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Color;

use Domm98CZ\Image\Exception\InvalidPixelBufferException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;

// Packed RGBA, 8 bits per channel, rows top to bottom: 4 bytes per pixel as one string (PHP arrays cost ~20x more).
final readonly class PixelBuffer
{
    public const BYTES_PER_PIXEL = 4;

    public function __construct(
        public Dimensions $dimensions,
        public string $bytes,
    ) {
        if (strlen($bytes) !== $dimensions->pixelCount() * self::BYTES_PER_PIXEL) {
            throw InvalidPixelBufferException::lengthMismatch($dimensions, strlen($bytes));
        }
    }

    public static function filled(Dimensions $dimensions, Color $color): self
    {
        return new self($dimensions, str_repeat(chr($color->red) . chr($color->green) . chr($color->blue) . chr($color->alpha), $dimensions->pixelCount()));
    }

    public function withBytes(string $bytes): self
    {
        return new self($this->dimensions, $bytes);
    }

    public function colorAt(Point $point): Color
    {
        $this->dimensions->assertContainsPoint($point);
        $offset = ($point->y * $this->dimensions->width + $point->x) * self::BYTES_PER_PIXEL;

        return new Color(ord($this->bytes[$offset]), ord($this->bytes[$offset + 1]), ord($this->bytes[$offset + 2]), ord($this->bytes[$offset + 3]));
    }

    public function stride(): int
    {
        return $this->dimensions->width * self::BYTES_PER_PIXEL;
    }
}
