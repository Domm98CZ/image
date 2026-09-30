<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;

final readonly class Sepia implements PixelOperationInterface
{
    public function area(Dimensions $image): Rectangle
    {
        return Rectangle::covering($image);
    }

    public function apply(PixelBuffer $pixels): PixelBuffer
    {
        $bytes = $pixels->bytes;
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += PixelBuffer::BYTES_PER_PIXEL) {
            $red = ord($bytes[$offset]);
            $green = ord($bytes[$offset + 1]);
            $blue = ord($bytes[$offset + 2]);
            $bytes[$offset] = chr(min(255, (int) round(0.393 * $red + 0.769 * $green + 0.189 * $blue)));
            $bytes[$offset + 1] = chr(min(255, (int) round(0.349 * $red + 0.686 * $green + 0.168 * $blue)));
            $bytes[$offset + 2] = chr(min(255, (int) round(0.272 * $red + 0.534 * $green + 0.131 * $blue)));
        }

        return $pixels->withBytes($bytes);
    }
}
