<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation\Gif;

use Domm98CZ\Image\Geometry\Dimensions;

/** @internal Byte-level building blocks of GIF89a. */
final class GifBytes
{
    public const TRAILER = "\x3B";
    private const COLOR_TABLE_FLAG = 0x80;
    private const INTERLACE_FLAG = 0x40;
    private const TRANSPARENCY_FLAG = 0x01;

    public static function header(Dimensions $screen, ?string $globalColorTable): string
    {
        $packed = $globalColorTable === null ? 0 : self::COLOR_TABLE_FLAG | 0x70 | self::tableSizeBits($globalColorTable);

        return 'GIF89a' . pack('vvCCC', $screen->width, $screen->height, $packed, 0, 0) . ($globalColorTable ?? '');
    }

    public static function loopExtension(int $loopCount): string
    {
        return "\x21\xFF\x0BNETSCAPE2.0\x03\x01" . pack('v', $loopCount) . "\x00";
    }

    public static function graphicControl(GifDisposal $disposal, int $delayCentiseconds, ?int $transparentIndex): string
    {
        $packed = ($disposal->value << 2) | ($transparentIndex === null ? 0 : self::TRANSPARENCY_FLAG);

        return "\x21\xF9\x04" . pack('CvC', $packed, $delayCentiseconds, $transparentIndex ?? 0) . "\x00";
    }

    public static function imageDescriptor(Dimensions $dimensions, ?string $localColorTable, bool $interlaced): string
    {
        $packed = ($interlaced ? self::INTERLACE_FLAG : 0)
            | ($localColorTable === null ? 0 : self::COLOR_TABLE_FLAG | self::tableSizeBits($localColorTable));

        return "\x2C" . pack('vvvvC', 0, 0, $dimensions->width, $dimensions->height, $packed) . ($localColorTable ?? '');
    }

    public static function colorTableLength(int $packedFields): int
    {
        return ($packedFields & self::COLOR_TABLE_FLAG) !== 0 ? 3 * (2 << ($packedFields & 0x07)) : 0;
    }

    // A table of 2^(n+1) entries is announced as n; tables read from files always have such a length.
    private static function tableSizeBits(string $colorTable): int
    {
        $entries = max(2, intdiv(strlen($colorTable), 3));

        return max(0, min(7, (int) ceil(log($entries, 2)) - 1));
    }
}
