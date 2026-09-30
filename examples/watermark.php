<?php declare(strict_types=1);

// Visible watermarks: a text logo and a drawn badge, each an ordinary Image composited with a position, an
// opacity and a blend mode. ImageFactory::text() renders text onto a canvas that fits every ink pixel exactly,
// so a corner inset lands where you expect. relativeWidth sizes the overlay from the image it lands on, so the
// same badge suits a thumbnail and a poster.
// Run: php examples/watermark.php

require __DIR__ . '/../vendor/autoload.php';

use Domm98CZ\Image\Blend\BlendMode;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\Stroke;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Position;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Watermark\VisibleWatermark;

$dir = sys_get_temp_dir();
$images = new ImageFactory();

// A 1200x800 gradient stands in for the photo.
$photo = $images->create(new Dimensions(1200, 800))
    ->applyPixels(static function (PixelBuffer $pixels): PixelBuffer {
        $bytes = '';
        for ($y = 0; $y < 800; ++$y) {
            for ($x = 0; $x < 1200; ++$x) {
                $bytes .= chr((int) (128 + 100 * sin($x / 97 + sin($y / 61)))) . chr((int) (128 + 90 * sin($y / 53 + $x / 211))) . chr((int) (160 + 80 * cos(($x + $y) / 149))) . "\xFF";
            }
        }

        return $pixels->withBytes($bytes);
    })
    ->toImage();

$logo = $images->text('(c) ACME 2026', new Font('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 36), Color::white(), padding: 8);
$signed = $dir . '/photo-signed.jpg';
$images->from($photo)
    ->watermark(new VisibleWatermark($logo, Position::inset(Anchor::BottomRight, 24), opacity: 0.6))
    ->save($signed, new JpegOutput(quality: 88));
printf("text logo %dx%d at 60%% in the bottom right corner -> %s\n", $logo->width(), $logo->height(), $signed);

$badge = $images->create(new Dimensions(400, 400))
    ->draw(static fn(Canvas $c): Canvas => $c
        ->ellipse(new Point(200, 200), new Dimensions(380, 380), Color::rgb(255, 80, 80), new Stroke(Color::rgb(120, 0, 0), 12))
        ->text('DEMO', new Point(90, 232), new Font('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 84), Color::rgb(60, 0, 0)))
    ->toImage();
$badged = $dir . '/photo-badged.png';
// Multiply can only darken, so the badge reads as ink on the photo instead of a sticker on top of it.
$images->from($photo)
    ->watermark(new VisibleWatermark($badge, Position::inset(Anchor::TopLeft, 20), opacity: 0.9, mode: BlendMode::Multiply, relativeWidth: 0.2))
    ->save($badged, new PngOutput());
printf("drawn badge scaled to 20%% of the width, multiplied in the top left corner -> %s\n", $badged);
