<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Support;

use Domm98CZ\Image\Image;

trait InkAssertions
{
    // Long enough for the drivers' advance rounding to add up, with accents that reach above the ascender.
    private const PANGRAM = 'Příliš žluťoučký kůň úpěl ďábelské ódy';

    // The padded render is the oracle: its ink must fill exactly the tight canvas, with the same pixel count.
    private static function assertTextImageIsTight(Image $tight, Image $padded, int $padding): void
    {
        [$tightCount] = self::inkOf($tight);
        [$paddedCount, $box] = self::inkOf($padded);

        self::assertGreaterThan(0, $paddedCount, 'text was rendered');
        self::assertSame([$padding, $padding, $padding + $tight->width() - 1, $padding + $tight->height() - 1], $box, 'ink of the padded render fills the tight canvas exactly');
        self::assertSame($paddedCount, $tightCount, 'no ink pixel is lost on the tight canvas');
    }

    /** @return array{int, array{int, int, int, int}|null} count of pixels with any alpha and their left, top, right, bottom */
    private static function inkOf(Image $image): array
    {
        $pixels = $image->pixels();
        $width = $pixels->dimensions->width;
        $count = 0;
        [$left, $top, $right, $bottom] = [PHP_INT_MAX, PHP_INT_MAX, -1, -1];
        for ($y = 0; $y < $pixels->dimensions->height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                if (ord($pixels->bytes[($y * $width + $x) * 4 + 3]) > 0) {
                    ++$count;
                    [$left, $top, $right, $bottom] = [min($left, $x), min($top, $y), max($right, $x), max($bottom, $y)];
                }
            }
        }

        return [$count, $count === 0 ? null : [$left, $top, $right, $bottom]];
    }
}
