<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Geometry\Dimensions;

final class InvalidPixelBufferException extends InvalidArgumentException
{
    public static function lengthMismatch(Dimensions $dimensions, int $length): self
    {
        return new self(sprintf(
            'A %dx%d RGBA buffer needs %d bytes, got %d.',
            $dimensions->width,
            $dimensions->height,
            $dimensions->pixelCount() * 4,
            $length,
        ));
    }
}
