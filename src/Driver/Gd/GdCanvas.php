<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Exception\DriverOperationFailedException;
use Domm98CZ\Image\Geometry\Dimensions;
use GdImage;

/** @internal */
final class GdCanvas
{
    public static function blank(Dimensions $dimensions, Color $background): GdImage
    {
        // Dimensions guarantees sides >= 1; max() only carries that invariant to static analysis.
        $call = CapturedCall::run(static fn(): GdImage|false => imagecreatetruecolor(max(1, $dimensions->width), max(1, $dimensions->height)));
        if (!$call->result instanceof GdImage) {
            throw DriverOperationFailedException::because(DriverName::Gd, sprintf('allocate a %dx%d canvas', $dimensions->width, $dimensions->height), $call->error);
        }
        $image = $call->result;
        self::prepare($image);
        imagefilledrectangle($image, 0, 0, $dimensions->width - 1, $dimensions->height - 1, GdColor::allocate($image, $background));

        return $image;
    }

    // Baseline for every handle: truecolor, alpha kept on save, pixels copied rather than blended.
    public static function prepare(GdImage $image): void
    {
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
    }

    public static function dimensions(GdImage $image): Dimensions
    {
        return new Dimensions(imagesx($image), imagesy($image));
    }
}
