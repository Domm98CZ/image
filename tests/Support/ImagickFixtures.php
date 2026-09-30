<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Support;

use Imagick;
use ImagickDraw;
use ImagickPixel;

// Animations authored by ImageMagick, used as an independent reference for the GIF container code.
// Every test calling this must carry #[RequiresPhpExtension('imagick')], whichever driver it exercises.
final class ImagickFixtures
{
    // 60x40: full red; blue 20x20 @10,10 (restore background); lime 20x20 @35,15 (restore previous);
    // yellow 10x10 @0,30 (keep); full-screen transparent frame with a black 10x10 square @50,0.
    public static function disposalShowcaseGif(): string
    {
        $animation = new Imagick();
        $animation->addImage(self::frame(60, 40, 'red', 0, 0, 10, Imagick::DISPOSE_NONE));
        $animation->addImage(self::frame(20, 20, 'blue', 10, 10, 20, Imagick::DISPOSE_BACKGROUND));
        $animation->addImage(self::frame(20, 20, 'lime', 35, 15, 30, Imagick::DISPOSE_PREVIOUS));
        $animation->addImage(self::frame(10, 10, 'yellow', 0, 30, 40, Imagick::DISPOSE_NONE));

        $overlay = new Imagick();
        $overlay->newImage(60, 40, new ImagickPixel('transparent'));
        $square = new ImagickDraw();
        $square->setFillColor('black');
        $square->rectangle(50, 0, 59, 9);
        $overlay->drawImage($square);
        $overlay->setImageFormat('gif');
        $overlay->setImageDelay(50);
        $overlay->setImageDispose(Imagick::DISPOSE_NONE);
        $animation->addImage($overlay);

        $animation->setIteratorIndex(0);
        $animation->setImageIterations(2);

        return $animation->getImagesBlob();
    }

    public static function coloredAnimation(string $format, int $width, int $height, int $frames): string
    {
        $animation = new Imagick();
        for ($index = 0; $index < $frames; ++$index) {
            $animation->addImage(self::frame($width, $height, sprintf('rgb(%d, %d, 0)', 60 * $index, 255 - 60 * $index), 0, 0, 10 * ($index + 1), Imagick::DISPOSE_NONE, $format));
        }
        $animation->setIteratorIndex(0);
        $animation->setImageIterations(0);

        return $animation->getImagesBlob();
    }

    private static function frame(int $width, int $height, string $color, int $x, int $y, int $delay, int $dispose, string $format = 'gif'): Imagick
    {
        $frame = new Imagick();
        $frame->newImage($width, $height, new ImagickPixel($color));
        $frame->setImageFormat($format);
        $frame->setImagePage(60, 40, $x, $y);
        $frame->setImageDelay($delay);
        $frame->setImageDispose($dispose);

        return $frame;
    }
}
