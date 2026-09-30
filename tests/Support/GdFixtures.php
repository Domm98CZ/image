<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Support;

use GdImage;
use RuntimeException;

// Real pixel fixtures built with raw GD; only tests may call GD directly.
final class GdFixtures
{
    public const TOP_LEFT = [255, 0, 0];
    public const TOP_RIGHT = [0, 255, 0];
    public const BOTTOM_LEFT = [0, 0, 255];
    public const BOTTOM_RIGHT = [255, 255, 0];

    public static function quadrantsPng(int $width, int $height, bool $transparentBottomRight = false): string
    {
        $image = self::canvas($width, $height);
        $halfWidth = intdiv($width, 2);
        $halfHeight = intdiv($height, 2);
        self::fill($image, 0, 0, $halfWidth - 1, $halfHeight - 1, self::TOP_LEFT);
        self::fill($image, $halfWidth, 0, $width - 1, $halfHeight - 1, self::TOP_RIGHT);
        self::fill($image, 0, $halfHeight, $halfWidth - 1, $height - 1, self::BOTTOM_LEFT);
        self::fill($image, $halfWidth, $halfHeight, $width - 1, $height - 1, self::BOTTOM_RIGHT, $transparentBottomRight ? 127 : 0);

        return self::png($image);
    }

    public static function solidPng(int $width, int $height, int $red, int $green, int $blue, int $gdAlpha): string
    {
        $image = self::canvas($width, $height);
        self::fill($image, 0, 0, $width - 1, $height - 1, [$red, $green, $blue], $gdAlpha);

        return self::png($image);
    }

    // One large low-frequency edge down the middle - a different coarse structure than quadrantsPng's cross.
    public static function verticalSplitPng(int $width, int $height): string
    {
        $image = self::canvas($width, $height);
        $half = intdiv($width, 2);
        self::fill($image, 0, 0, $half - 1, $height - 1, self::TOP_LEFT);
        self::fill($image, $half, 0, $width - 1, $height - 1, self::BOTTOM_LEFT);

        return self::png($image);
    }

    private static function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        if ($image === false) {
            throw new RuntimeException('Cannot allocate fixture.');
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    /** @param array{int, int, int} $rgb */
    private static function fill(GdImage $image, int $left, int $top, int $right, int $bottom, array $rgb, int $gdAlpha = 0): void
    {
        $color = imagecolorallocatealpha($image, max(0, min(255, $rgb[0])), max(0, min(255, $rgb[1])), max(0, min(255, $rgb[2])), max(0, min(127, $gdAlpha)));
        imagefilledrectangle($image, $left, $top, $right, $bottom, (int) $color);
    }

    private static function png(GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
