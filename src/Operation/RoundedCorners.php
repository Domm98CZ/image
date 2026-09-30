<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;

final readonly class RoundedCorners implements PixelOperationInterface
{
    public function __construct(
        public int $radius,
    ) {
        ColorAdjustment::assertRange('RoundedCorners', 'radius', $radius, 0, Dimensions::MAX_SIDE);
    }

    public function area(Dimensions $image): Rectangle
    {
        return Rectangle::covering($image);
    }

    public function apply(PixelBuffer $pixels): PixelBuffer
    {
        $radius = min($this->radius, intdiv(min($pixels->dimensions->width, $pixels->dimensions->height), 2));
        if ($radius === 0) {
            return $pixels;
        }
        $bytes = $pixels->bytes;
        foreach (range(0, $pixels->dimensions->height - 1) as $y) {
            foreach (range(0, $pixels->dimensions->width - 1) as $x) {
                if (!self::outsideCorner($x, $y, $pixels->dimensions, $radius)) {
                    continue;
                }
                $bytes[($y * $pixels->dimensions->width + $x) * PixelBuffer::BYTES_PER_PIXEL + 3] = chr(0);
            }
        }

        return $pixels->withBytes($bytes);
    }

    private static function outsideCorner(int $x, int $y, Dimensions $dimensions, int $radius): bool
    {
        $center = $radius - 0.5;
        if ($x < $radius && $y < $radius) {
            return ($x - $center) ** 2 + ($y - $center) ** 2 >= $radius ** 2;
        }
        if ($x >= $dimensions->width - $radius && $y < $radius) {
            return ($x - ($dimensions->width - $radius - 0.5)) ** 2 + ($y - $center) ** 2 >= $radius ** 2;
        }
        if ($x < $radius && $y >= $dimensions->height - $radius) {
            return ($x - $center) ** 2 + ($y - ($dimensions->height - $radius - 0.5)) ** 2 >= $radius ** 2;
        }
        if ($x >= $dimensions->width - $radius && $y >= $dimensions->height - $radius) {
            return ($x - ($dimensions->width - $radius - 0.5)) ** 2 + ($y - ($dimensions->height - $radius - 0.5)) ** 2 >= $radius ** 2;
        }

        return false;
    }
}
