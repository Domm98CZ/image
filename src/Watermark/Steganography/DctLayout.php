<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark\Steganography;

use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Watermark\SecretKey;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/** @internal Which 8x8 blocks carry which frame bit; fixed frame size so a reader can derive the same layout. */
final readonly class DctLayout
{
    public const PAYLOAD_BYTES = 24;
    // Header (6) + fixed payload (24) + 8-byte check = 38 bytes.
    public const FRAME_BITS = 304;
    private const MIN_REPEATS = 3;
    // Enough votes per bit for JPEG noise; more would only cost time on large images.
    private const MAX_REPEATS = 15;
    // Public seed for unkeyed layouts: spreads blocks over the image instead of filling the top rows.
    private const PUBLIC_SEED = 'domm98cz/image dct layout v1';

    /** @param list<Point> $blocks */
    private function __construct(
        private array $blocks,
    ) {}

    public static function frame(): Frame
    {
        return new Frame('DD', 8);
    }

    public static function for(Dimensions $image, ?SecretKey $key): self
    {
        $columns = intdiv($image->width, 8);
        $blockCount = $columns * intdiv($image->height, 8);
        $repeats = min(self::MAX_REPEATS, intdiv($blockCount, self::FRAME_BITS));
        if ($repeats < self::MIN_REPEATS) {
            throw InvalidWatermarkException::insufficientCapacity('DCT', self::FRAME_BITS * self::MIN_REPEATS, $blockCount);
        }

        $seed = $key === null ? hash('sha256', self::PUBLIC_SEED, true) : $key->derive('dct-layout');
        $order = (new Randomizer(new Xoshiro256StarStar($seed)))->shuffleArray(range(0, $blockCount - 1));
        $blocks = [];
        foreach (array_slice($order, 0, $repeats * self::FRAME_BITS) as $block) {
            $blocks[] = new Point(($block % $columns) * 8, intdiv($block, $columns) * 8);
        }

        return new self($blocks);
    }

    // Block i carries frame bit i mod FRAME_BITS.
    /** @return list<Point> */
    public function blocks(): array
    {
        return $this->blocks;
    }

    // The payload slot is fixed-size so the reader knows the layout before it knows the payload length.
    public static function pad(string $frame): string
    {
        return str_pad($frame, intdiv(self::FRAME_BITS, 8), "\0");
    }
}
