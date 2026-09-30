<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick;

use Domm98CZ\Image\Color\Color;
use Imagick;
use ImagickPixel;

/** @internal */
final class ImagickColor
{
    public static function toPixel(Color $color): ImagickPixel
    {
        return new ImagickPixel(sprintf('rgba(%d, %d, %d, %F)', $color->red, $color->green, $color->blue, $color->opacity()));
    }

    public static function fromPixel(ImagickPixel $pixel): Color
    {
        $channel = static fn(int $channel): int => max(0, min(255, (int) round($pixel->getColorValue($channel) * 255)));

        return new Color($channel(Imagick::COLOR_RED), $channel(Imagick::COLOR_GREEN), $channel(Imagick::COLOR_BLUE), $channel(Imagick::COLOR_ALPHA));
    }
}
