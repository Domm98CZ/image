<?php declare(strict_types=1);

// A bar chart drawn over a photo, the way a "stats card" for social media is built: open, draw, save. The
// panel is a translucent white rectangle, so the photo stays visible underneath; the bars are opaque, so their
// colours are exact whatever the photo had there. The whole chart is one draw() step.
// Run: php examples/chart-overlay-on-photo.php

require __DIR__ . '/../vendor/autoload.php';

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\Stroke;
use Domm98CZ\Image\Format\Output\JpegOutput;
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

$data = ['Jan' => 42, 'Feb' => 65, 'Mar' => 30, 'Apr' => 88, 'May' => 74, 'Jun' => 96];
$colors = [Color::rgb(66, 133, 244), Color::rgb(219, 68, 55), Color::rgb(244, 180, 0), Color::rgb(15, 157, 88), Color::rgb(171, 71, 188), Color::rgb(0, 172, 193)];
$title = new Font('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 32);
$label = new Font('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 18);

$panel = new Rectangle(new Point(80, 120), new Dimensions(1040, 560));
$plotLeft = 160;
$plotBottom = 610;
$plotHeight = 380;
$max = max($data);

$chart = static function (Canvas $c) use ($data, $colors, $title, $label, $panel, $plotLeft, $plotBottom, $plotHeight, $max): Canvas {
    $c = $c
        ->rectangle($panel, Color::rgba(255, 255, 255, 0.82), new Stroke(Color::rgba(0, 0, 0, 0.35), 2))
        ->text('Monthly visitors (thousands)', new Point(110, 172), $title, Color::rgb(30, 30, 30))
        ->line(new Point($plotLeft - 10, $plotBottom), new Point(1090, $plotBottom), new Stroke(Color::rgb(60, 60, 60), 2))
        ->line(new Point($plotLeft - 10, $plotBottom), new Point($plotLeft - 10, $plotBottom - $plotHeight - 10), new Stroke(Color::rgb(60, 60, 60), 2));
    foreach ([25, 50, 75, 100] as $tick) {
        $y = $plotBottom - (int) round($plotHeight * $tick / 100);
        $c = $c
            ->line(new Point($plotLeft - 10, $y), new Point(1090, $y), new Stroke(Color::rgba(0, 0, 0, 0.12), 1))
            ->text((string) $tick, new Point($plotLeft - 50, $y + 6), $label, Color::rgb(80, 80, 80));
    }
    foreach (array_values($data) as $index => $value) {
        $x = $plotLeft + $index * 155;
        $height = (int) round($plotHeight * $value / $max);
        $c = $c
            ->rectangle(new Rectangle(new Point($x, $plotBottom - $height), new Dimensions(100, $height)), $colors[$index])
            ->text(array_keys($data)[$index], new Point($x + 30, $plotBottom + 30), $label, Color::rgb(40, 40, 40))
            ->text((string) $value, new Point($x + 36, $plotBottom - $height - 10), $label, Color::rgb(20, 20, 20));
    }

    return $c;
};

$card = $dir . '/chart-card.jpg';
$encoded = ImageBuilder::open($photo)
    ->draw($chart)
    ->save($card, new JpegOutput(quality: 88, progressive: true));

// A spot inside the panel, right of the last bar: the photo shows through, lightened by the panel.
$spot = new Point(1075, 300);
$before = ImageBuilder::open($photo)->toImage()->colorAt($spot);
$after = ImageBuilder::open($card)->toImage()->colorAt($spot);
printf("%d bytes written to %s\n", $encoded->byteCount(), $card);
printf("photo rgb(%d, %d, %d) under the translucent panel became rgb(%d, %d, %d)\n", $before->red, $before->green, $before->blue, $after->red, $after->green, $after->blue);
