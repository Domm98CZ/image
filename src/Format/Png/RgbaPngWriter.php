<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Png;

use Domm98CZ\Image\Color\PixelBuffer;

// Uncompressed (stored) RGBA PNG: the fastest lossless way to hand raw pixels to a decoder like GD.
final readonly class RgbaPngWriter
{
    private const SIGNATURE = "\x89PNG\r\n\x1A\n";
    private const BIT_DEPTH = 8;
    private const COLOR_TYPE_RGBA = 6;
    private const FILTER_NONE = "\0";

    public function write(PixelBuffer $pixels): string
    {
        $stride = $pixels->stride();
        $rows = [];
        for ($y = 0; $y < $pixels->dimensions->height; ++$y) {
            $rows[] = self::FILTER_NONE . substr($pixels->bytes, $y * $stride, $stride);
        }
        $header = pack('NNCCCCC', $pixels->dimensions->width, $pixels->dimensions->height, self::BIT_DEPTH, self::COLOR_TYPE_RGBA, 0, 0, 0);

        return self::SIGNATURE
            . self::chunk('IHDR', $header)
            . self::chunk('IDAT', (string) gzcompress(implode('', $rows), 0))
            . self::chunk('IEND', '');
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}
