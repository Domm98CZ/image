<?php declare(strict_types=1);

// Shapes and text on a blank canvas. A Canvas is an immutable collector: every call returns a new one and
// nothing is painted until the pipeline runs. Colours with an alpha blend with what is below them on either
// driver, and text drawn with the same font looks the same whichever driver renders it: lines separated by
// "\n" are stacked 1.2 em apart and a glyph the font lacks is drawn as U+FFFD on both.
// Run: php examples/draw-shapes-and-text.php

require __DIR__ . '/../vendor/autoload.php';

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\Stroke;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\ImageBuilder;

// Any TrueType/OpenType file works; the library bundles no font.
$regular = new Font('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 20);
$bold = new Font('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 28);

$image = ImageBuilder::create(new Dimensions(640, 400), Color::rgb(245, 245, 240))
    ->draw(static fn(Canvas $c): Canvas => $c
        ->rectangle(new Rectangle(new Point(40, 40), new Dimensions(200, 120)), Color::rgb(220, 60, 60), new Stroke(Color::black(), 4))
        // stroke only, half transparent: the background shows through
        ->rectangle(new Rectangle(new Point(280, 40), new Dimensions(200, 120)), null, new Stroke(Color::rgba(0, 80, 200, 0.5), 12))
        ->ellipse(new Point(140, 280), new Dimensions(180, 120), Color::rgb(60, 170, 90))
        ->polygon([new Point(520, 60), new Point(610, 160), new Point(500, 160)], Color::rgb(250, 190, 30), new Stroke(Color::rgb(120, 80, 0), 2))
        ->line(new Point(20, 380), new Point(620, 380), new Stroke(Color::black(), 1))
        ->text('domm98cz/image', new Point(40, 210), $bold, Color::rgb(20, 20, 20))
        ->text("two lines,\n1.2 em apart", new Point(300, 250), $regular, Color::rgb(90, 90, 90))
        ->text('rotated', new Point(560, 380), $regular, Color::rgb(0, 100, 0), Angle::counterClockwise(90)))
    ->toImage();

$path = sys_get_temp_dir() . '/shapes.png';
$png = ImageBuilder::from($image)->save($path, new PngOutput());

$blended = $image->colorAt(new Point(280, 100));
printf("drawn on %s, %d bytes written to %s\n", $image->driverName()->value, $png->byteCount(), $path);
printf("translucent blue stroke over the background: rgb(%d, %d, %d)\n", $blended->red, $blended->green, $blended->blue);
