<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Geometry\Dimensions;
use GdImage;

/** @internal GD only has a 3x3 kernel; larger sigmas are built from repeated passes on a downscaled copy. */
final class GdGaussianBlur
{
    // Repeated 3x3 [1 2 1] passes add variance 0.5 each; beyond this sigma the pass count gets expensive.
    private const MAX_DIRECT_SIGMA = 3.0;
    private const KERNEL = [[1, 2, 1], [2, 4, 2], [1, 2, 1]];

    public static function apply(GdImage $image, float $sigma): GdImage
    {
        if ($sigma <= self::MAX_DIRECT_SIGMA) {
            self::passes($image, self::passCount($sigma));

            return $image;
        }

        // Blur at 1/k scale where the remaining sigma is small, then scale back (within ~20 levels of a true Gaussian at edges).
        $original = GdCanvas::dimensions($image);
        $factor = $sigma / self::MAX_DIRECT_SIGMA;
        $small = GdCanvas::blank(new Dimensions(max(1, (int) round($original->width / $factor)), max(1, (int) round($original->height / $factor))), Color::transparent());
        imagecopyresampled($small, $image, 0, 0, 0, 0, imagesx($small), imagesy($small), $original->width, $original->height);
        self::passes($small, self::passCount(self::MAX_DIRECT_SIGMA * 0.92));
        $restored = GdCanvas::blank($original, Color::transparent());
        imagecopyresampled($restored, $small, 0, 0, 0, 0, $original->width, $original->height, imagesx($small), imagesy($small));

        return $restored;
    }

    private static function passCount(float $sigma): int
    {
        return max(1, (int) round(2 * $sigma * $sigma));
    }

    private static function passes(GdImage $image, int $count): void
    {
        for ($pass = 0; $pass < $count; ++$pass) {
            // 0.5 offset rounds each pass; truncating would darken by ~0.5 levels per pass.
            imageconvolution($image, self::KERNEL, 16, 0.5);
        }
    }
}
