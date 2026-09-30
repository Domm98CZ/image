<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Analysis;

use Domm98CZ\Image\Color\PixelBuffer;

// Per-channel pixel counts, one bucket per 0-255 value.
final readonly class Histogram
{
    /**
     * @param array<int, int> $red 256 buckets, one per 0-255 value
     * @param array<int, int> $green
     * @param array<int, int> $blue
     * @param array<int, int> $alpha
     */
    private function __construct(
        public array $red,
        public array $green,
        public array $blue,
        public array $alpha,
    ) {}

    public static function of(PixelBuffer $pixels): self
    {
        $red = array_fill(0, 256, 0);
        $green = array_fill(0, 256, 0);
        $blue = array_fill(0, 256, 0);
        $alpha = array_fill(0, 256, 0);
        $bytes = $pixels->bytes;
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += PixelBuffer::BYTES_PER_PIXEL) {
            ++$red[ord($bytes[$offset])];
            ++$green[ord($bytes[$offset + 1])];
            ++$blue[ord($bytes[$offset + 2])];
            ++$alpha[ord($bytes[$offset + 3])];
        }

        return new self($red, $green, $blue, $alpha);
    }
}
