<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd;

use GdImage;

/** @internal */
final class GdOpacity
{
    private const GD_ALPHA_MASK = 0x7F000000;

    // GD has no bulk alpha query; the loop stops at the first translucent pixel (~25 ns per opaque pixel, no JIT).
    public static function isFullyOpaque(GdImage $image): bool
    {
        if (!imageistruecolor($image)) {
            return false;
        }
        [$width, $height] = [imagesx($image), imagesy($image)];
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                if (((int) imagecolorat($image, $x, $y) & self::GD_ALPHA_MASK) !== 0) {
                    return false;
                }
            }
        }

        return true;
    }
}
