<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark\Steganography;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Watermark\SecretKey;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/** @internal Byte offsets of R/G/B channels in embedding order: pixel order, or a key-seeded walk when keyed. */
final class LsbSlots
{
    private int $sequential = 0;

    /** @var array<int, true> */
    private array $used = [];

    private function __construct(
        private readonly PixelBuffer $pixels,
        private readonly ?Randomizer $walk,
        private readonly int $opaquePixels,
    ) {}

    public static function for(PixelBuffer $pixels, ?SecretKey $key): self
    {
        $opaque = 0;
        for ($offset = 3, $length = strlen($pixels->bytes); $offset < $length; $offset += 4) {
            if ($pixels->bytes[$offset] === "\xFF") {
                ++$opaque;
            }
        }
        $walk = $key === null ? null : new Randomizer(new Xoshiro256StarStar($key->derive('lsb-walk')));

        return new self($pixels, $walk, $opaque);
    }

    // Keyed walks use rejection sampling, so they keep half the opaque channels in reserve to stay fast.
    public function capacity(): int
    {
        return $this->walk === null ? $this->opaquePixels * 3 : intdiv($this->opaquePixels * 3, 2);
    }

    public function next(): int
    {
        return $this->walk === null ? $this->nextSequential() : $this->nextRandom($this->walk);
    }

    private function nextSequential(): int
    {
        while (true) {
            $pixel = intdiv($this->sequential, 3);
            $offset = $pixel * 4 + $this->sequential % 3;
            ++$this->sequential;
            if ($this->pixels->bytes[$pixel * 4 + 3] === "\xFF") {
                return $offset;
            }
        }
    }

    private function nextRandom(Randomizer $walk): int
    {
        $slots = $this->pixels->dimensions->pixelCount() * 3;
        while (true) {
            $slot = $walk->getInt(0, $slots - 1);
            $pixel = intdiv($slot, 3);
            if (array_key_exists($slot, $this->used) || $this->pixels->bytes[$pixel * 4 + 3] !== "\xFF") {
                continue;
            }
            $this->used[$slot] = true;

            return $pixel * 4 + $slot % 3;
        }
    }
}
