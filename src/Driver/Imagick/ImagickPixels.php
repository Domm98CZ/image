<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Exception\DriverOperationFailedException;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Imagick;

/** @internal Bulk RGBA access for Imagick images (callers validate areas). */
final class ImagickPixels
{
    public static function read(Imagick $image, Rectangle $area): PixelBuffer
    {
        $bytes = ImagickCall::run('export pixels', static function () use ($image, $area): string {
            $region = $image->getImageRegion($area->dimensions->width, $area->dimensions->height, $area->left(), $area->top());
            $region->setImageFormat('rgba');
            $region->setImageDepth(8);

            return $region->getImageBlob();
        });
        if (strlen($bytes) !== $area->dimensions->pixelCount() * PixelBuffer::BYTES_PER_PIXEL) {
            throw DriverOperationFailedException::because(DriverName::Imagick, 'export pixels', sprintf('got %d bytes', strlen($bytes)));
        }

        return new PixelBuffer($area->dimensions, $bytes);
    }

    public static function write(Imagick $image, PixelBuffer $pixels, Point $origin): void
    {
        ImagickCall::run('import pixels', static function () use ($image, $pixels, $origin): void {
            $tile = new Imagick();
            $tile->setSize($pixels->dimensions->width, $pixels->dimensions->height);
            $tile->setOption('depth', '8');
            $tile->setFormat('rgba');
            $tile->readImageBlob($pixels->bytes);
            if (!$image->getImageAlphaChannel()) {
                $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
            }
            $image->compositeImage($tile, Imagick::COMPOSITE_COPY, $origin->x, $origin->y);
        });
    }
}
