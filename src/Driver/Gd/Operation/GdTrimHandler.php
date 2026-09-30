<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Driver\Gd\GdCanvas;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Operation\Trim;
use GdImage;

/** @implements GdOperationHandlerInterface<Trim> */
final readonly class GdTrimHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Trim::class;
    }

    public function support(): Support
    {
        return Support::PhpFallback;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $border = (int) imagecolorat($image, 0, 0);
        $top = 0;
        while ($top < $height && self::rowMatches($image, $top, 0, $width - 1, $border)) {
            ++$top;
        }
        if ($top === $height) {
            $trimmed = GdCanvas::blank(new Dimensions(1, 1), Color::transparent());
            imagecopy($trimmed, $image, 0, 0, 0, 0, 1, 1);

            return $trimmed;
        }
        $bottom = $height - 1;
        while (self::rowMatches($image, $bottom, 0, $width - 1, $border)) {
            --$bottom;
        }
        $left = 0;
        while ($left < $width && self::columnMatches($image, $left, $top, $bottom, $border)) {
            ++$left;
        }
        $right = $width - 1;
        while (self::columnMatches($image, $right, $top, $bottom, $border)) {
            --$right;
        }
        $dimensions = new Dimensions($right - $left + 1, $bottom - $top + 1);
        $trimmed = GdCanvas::blank($dimensions, Color::transparent());
        imagecopy($trimmed, $image, 0, 0, $left, $top, $dimensions->width, $dimensions->height);

        return $trimmed;
    }

    private static function rowMatches(GdImage $image, int $y, int $left, int $right, int $color): bool
    {
        for ($x = $left; $x <= $right; ++$x) {
            if (imagecolorat($image, $x, $y) !== $color) {
                return false;
            }
        }

        return true;
    }

    private static function columnMatches(GdImage $image, int $x, int $top, int $bottom, int $color): bool
    {
        for ($y = $top; $y <= $bottom; ++$y) {
            if (imagecolorat($image, $x, $y) !== $color) {
                return false;
            }
        }

        return true;
    }
}
