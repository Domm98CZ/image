<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Image;

final readonly class Mask implements PixelOperationInterface
{
    public function __construct(
        public Image $mask,
    ) {}

    public function area(Dimensions $image): Rectangle
    {
        return Rectangle::covering($image);
    }

    public function apply(PixelBuffer $pixels): PixelBuffer
    {
        $mask = $this->mask->pixels();
        if (!$pixels->dimensions->equals($mask->dimensions)) {
            throw InvalidOperationException::maskDimensionsMismatch($pixels->dimensions, $mask->dimensions);
        }
        $bytes = $pixels->bytes;
        for ($offset = 3, $length = strlen($bytes); $offset < $length; $offset += PixelBuffer::BYTES_PER_PIXEL) {
            $bytes[$offset] = chr((int) round(ord($bytes[$offset]) * ord($mask->bytes[$offset]) / 255));
        }

        return $pixels->withBytes($bytes);
    }
}
