<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;

final readonly class Opacity implements PixelOperationInterface
{
    public function __construct(
        public float $opacity,
    ) {
        ColorAdjustment::assertRange('Opacity', 'opacity', $opacity, 0, 1);
    }

    public function area(Dimensions $image): Rectangle
    {
        return Rectangle::covering($image);
    }

    public function apply(PixelBuffer $pixels): PixelBuffer
    {
        $bytes = $pixels->bytes;
        for ($offset = 3, $length = strlen($bytes); $offset < $length; $offset += PixelBuffer::BYTES_PER_PIXEL) {
            $bytes[$offset] = chr((int) round(ord($bytes[$offset]) * $this->opacity));
        }

        return $pixels->withBytes($bytes);
    }
}
