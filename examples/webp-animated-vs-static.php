<?php declare(strict_types=1);

// Static WebP (lossy and lossless) works on any driver. Animated WebP is a codec, not a pipeline step, and only
// Imagick has one: frames cropped from a decoded file sit on GD (decoding and cropping are native on both, ties go
// to the first driver), the codec hands them to Imagick itself. With GD forced (or on a GD-only server) the same
// frames are refused as WebP but still encode as an animated GIF.
// Run: php examples/webp-animated-vs-static.php

require __DIR__ . '/../vendor/autoload.php';

use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\AnimationBuilder;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\ImageBuilder;
use Domm98CZ\Image\ImageFactory;

$dir = sys_get_temp_dir();
$photo = $dir . '/gradient.png';

// A 480x320 gradient stands in for a photo.
ImageBuilder::create(new Dimensions(480, 320))
    ->applyPixels(static function (PixelBuffer $pixels): PixelBuffer {
        $bytes = '';
        for ($y = 0; $y < 320; ++$y) {
            for ($x = 0; $x < 480; ++$x) {
                $bytes .= chr((int) (128 + 100 * sin($x / 41))) . chr((int) (128 + 90 * sin($y / 29 + $x / 97))) . chr((int) (160 + 80 * cos(($x + $y) / 67))) . "\xFF";
            }
        }

        return $pixels->withBytes($bytes);
    })
    ->save($photo);

$lossy = ImageBuilder::open($photo)->save($dir . '/still-lossy.webp', new WebpOutput(quality: 80));
$lossless = ImageBuilder::open($photo)->save($dir . '/still-lossless.webp', new WebpOutput(lossless: true));
printf("static WebP:   lossy q80 %d bytes, lossless %d bytes\n", $lossy->byteCount(), $lossless->byteCount());

$frames = [];
for ($i = 0; $i < 6; ++$i) {
    $frame = ImageBuilder::open($photo)->crop(new Rectangle(new Point($i * 30, 40), new Dimensions(300, 200)))->toImage();
    $frames[] = new Frame($frame, 120);
}
printf("frames:        %d, held by %s\n", count($frames), $frames[0]->image->driverName()->value);

try {
    $animated = AnimationBuilder::fromFrames($frames, playCount: 0)->save($dir . '/animated.webp', new WebpOutput(quality: 85));
    $decoded = AnimationBuilder::open($dir . '/animated.webp')->toAnimation();
    printf("animated WebP: %d bytes, re-opened with %d frames on %s\n", $animated->byteCount(), $decoded->frameCount(), $decoded->frames[0]->image->driverName()->value);
} catch (UnsupportedFormatException $exception) {
    printf("animated WebP: %s\n", $exception->getMessage());
}

$gdOnly = new ImageFactory(Configuration::default()->withForcedDriver(DriverName::Gd));
try {
    $gdOnly->animation($frames)->save($dir . '/animated-gd.webp');
} catch (UnsupportedFormatException $exception) {
    printf("GD forced:     %s\n", $exception->getMessage());
}
$gif = $gdOnly->animation($frames)->save($dir . '/animated-gd.gif');
printf("GD forced:     animated GIF instead, %d bytes\n", $gif->byteCount());
