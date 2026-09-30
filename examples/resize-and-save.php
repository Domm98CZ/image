<?php declare(strict_types=1);

// Open, orient, resize, save. Every fluent call returns a new builder and nothing is decoded until a terminal
// call (save, encode, toImage), yet the input is validated at open(), so a broken or hostile file fails there.
// save() takes the format from the extension, writes a temporary file next to the target and renames it, so no
// reader ever sees half an image.
// Run: php examples/resize-and-save.php

require __DIR__ . '/../vendor/autoload.php';

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\ImageBuilder;

$dir = sys_get_temp_dir();
$photo = $dir . '/photo.jpg';

// A synthetic 1200x800 "photo" (plasma sky, sun, hill), so the script needs no input file.
ImageBuilder::create(new Dimensions(1200, 800))
    ->applyPixels(static function (PixelBuffer $pixels): PixelBuffer {
        $bytes = '';
        for ($y = 0; $y < 800; ++$y) {
            for ($x = 0; $x < 1200; ++$x) {
                $bytes .= chr((int) (128 + 100 * sin($x / 97 + sin($y / 61))))
                    . chr((int) (128 + 90 * sin($y / 53 + $x / 211)))
                    . chr((int) (160 + 80 * cos(($x + $y) / 149)))
                    . "\xFF";
            }
        }

        return $pixels->withBytes($bytes);
    })
    ->draw(static fn(Canvas $c): Canvas => $c
        ->ellipse(new Point(900, 220), new Dimensions(180, 180), Color::rgb(255, 220, 90))
        ->polygon([new Point(0, 800), new Point(0, 620), new Point(400, 440), new Point(800, 640), new Point(1200, 500), new Point(1200, 800)], Color::rgb(40, 60, 80)))
    ->save($photo, new JpegOutput(quality: 92));

$source = ImageBuilder::open($photo)->autoOrient();

$source->fitInside(new Dimensions(600, 600))->save($dir . '/photo-600.jpg');
$source->thumbnail(new Dimensions(300, 300), Anchor::Center)->save($dir . '/photo-thumb.webp');
$source->crop(new Rectangle(new Point(700, 100), new Dimensions(400, 300)))->grayscale()->save($dir . '/photo-sun.png', new PngOutput(compressionLevel: 9));

foreach (['photo.jpg', 'photo-600.jpg', 'photo-thumb.webp', 'photo-sun.png'] as $name) {
    $image = ImageBuilder::open($dir . '/' . $name)->toImage();
    printf("%-18s %4dx%-4d %7d bytes  %s\n", $name, $image->width(), $image->height(), filesize($dir . '/' . $name), $dir . '/' . $name);
}
