<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd;

use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use GdImage;

/** @internal Bounding box of every pixel that differs from a known background (GD has no native trim that reports offsets). */
final class GdInk
{
    // Scans inward from each edge and stops at the first ink, so a canvas with a small margin costs a few thousand reads.
    public static function box(GdImage $image, int $background): ?Rectangle
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $top = self::firstInkedRow($image, $background, range(0, $height - 1), 0, $width - 1);
        if ($top === null) {
            return null;
        }
        $bottom = self::firstInkedRow($image, $background, range($height - 1, $top), 0, $width - 1) ?? $top;
        $left = self::firstInkedColumn($image, $background, range(0, $width - 1), $top, $bottom) ?? 0;
        $right = self::firstInkedColumn($image, $background, range($width - 1, $left), $top, $bottom) ?? $left;

        return new Rectangle(new Point($left, $top), new Dimensions($right - $left + 1, $bottom - $top + 1));
    }

    public static function touchesEdge(Rectangle $box, Dimensions $canvas): bool
    {
        return $box->left() === 0 || $box->top() === 0 || $box->rightExclusive() === $canvas->width || $box->bottomExclusive() === $canvas->height;
    }

    /** @param list<int> $rows */
    private static function firstInkedRow(GdImage $image, int $background, array $rows, int $fromX, int $toX): ?int
    {
        foreach ($rows as $y) {
            for ($x = $fromX; $x <= $toX; ++$x) {
                if (imagecolorat($image, $x, $y) !== $background) {
                    return $y;
                }
            }
        }

        return null;
    }

    /** @param list<int> $columns */
    private static function firstInkedColumn(GdImage $image, int $background, array $columns, int $fromY, int $toY): ?int
    {
        foreach ($columns as $x) {
            for ($y = $fromY; $y <= $toY; ++$y) {
                if (imagecolorat($image, $x, $y) !== $background) {
                    return $x;
                }
            }
        }

        return null;
    }
}
