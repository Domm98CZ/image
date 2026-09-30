<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Driver\Gd\Format\GdCodec;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Png\RgbaPngWriter;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use GdImage;

/** @internal Bulk RGBA access for GD images (callers validate areas). */
final class GdPixels
{
    // GD has no bulk pixel export; a per-pixel loop is the only lossless path (measured ~1.1 s per 12 MP).
    public static function read(GdImage $image, Rectangle $area): PixelBuffer
    {
        $rows = [];
        for ($y = $area->top(); $y < $area->bottomExclusive(); ++$y) {
            $row = [];
            for ($x = $area->left(); $x < $area->rightExclusive(); ++$x) {
                $argb = (int) imagecolorat($image, $x, $y);
                $gdAlpha = ($argb >> 24) & 0x7F;
                $row[] = pack('N', (($argb & 0xFFFFFF) << 8) | (255 - ($gdAlpha << 1) - ($gdAlpha >> 6)));
            }
            $rows[] = implode('', $row);
        }

        return new PixelBuffer($area->dimensions, implode('', $rows));
    }

    // A stored PNG decoded by GD is the fastest lossless way in (measured ~0.44 s per 12 MP).
    public static function write(GdImage $target, PixelBuffer $pixels, Point $origin): void
    {
        $tile = GdCodec::decode(FormatName::Png, (new RgbaPngWriter())->write($pixels));
        GdCanvas::prepare($tile);
        imagealphablending($target, false);
        imagecopy($target, $tile, $origin->x, $origin->y, 0, 0, $pixels->dimensions->width, $pixels->dimensions->height);
    }
}
