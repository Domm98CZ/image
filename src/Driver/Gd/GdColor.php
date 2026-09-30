<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd;

use Domm98CZ\Image\Color\Color;
use GdImage;

/** @internal GD stores alpha as 7 bits, inverted (0 = opaque, 127 = transparent). */
final class GdColor
{
    public static function allocate(GdImage $image, Color $color): int
    {
        $index = imagecolorallocatealpha(
            $image,
            self::channel($color->red),
            self::channel($color->green),
            self::channel($color->blue),
            max(0, min(127, 127 - ($color->alpha >> 1))),
        );

        // Truecolor images never run out of palette slots, so false cannot happen for handles we create.
        return $index === false ? 0 : $index;
    }

    // Color guarantees 0-255; clamping only carries that invariant to static analysis.
    /** @return int<0, 255> */
    private static function channel(int $value): int
    {
        return max(0, min(255, $value));
    }

    public static function fromTruecolor(int $argb): Color
    {
        $gdAlpha = ($argb >> 24) & 0x7F;

        return new Color(($argb >> 16) & 0xFF, ($argb >> 8) & 0xFF, $argb & 0xFF, 255 - ($gdAlpha << 1) - ($gdAlpha >> 6));
    }
}
