<?php declare(strict_types=1);

// An animated GIF from frames drawn with the library, then re-opened and transformed. A Frame is a fully
// composited picture shown for a delay; the same fluent operations as on ImageBuilder run on every frame,
// and GIF works on any driver.
// Run: php examples/gif-animation.php

require __DIR__ . '/../vendor/autoload.php';

use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\AnimationBuilder;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\Stroke;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\ImageBuilder;

$font = new Font('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 14);
$dir = sys_get_temp_dir();

$frames = [];
for ($i = 0; $i < 12; ++$i) {
    $x = 30 + $i * 24;
    $y = 106 - (int) round(70 * abs(sin($i * M_PI / 6)));
    $image = ImageBuilder::create(new Dimensions(320, 140), Color::rgb(30, 30, 40))
        ->draw(static fn(Canvas $c): Canvas => $c
            ->line(new Point(0, 120), new Point(320, 120), new Stroke(Color::rgb(90, 90, 110), 2))
            ->ellipse(new Point($x, $y), new Dimensions(28, 28), Color::rgb(255, 140, 0))
            ->text(sprintf('frame %2d', $i + 1), new Point(8, 22), $font, Color::white()))
        ->toImage();
    // delay in milliseconds; the last frame lingers before the loop restarts
    $frames[] = new Frame($image, $i === 11 ? 600 : 80);
}

$bounce = $dir . '/bounce.gif';
$encoded = AnimationBuilder::fromFrames($frames, playCount: 0)->save($bounce);    // 0 = loop forever
printf("%s: %d frames, %d bytes\n", $bounce, count($frames), $encoded->byteCount());

$small = $dir . '/bounce-small.gif';
AnimationBuilder::open($bounce)
    ->scale(0.5)
    ->withFrameDelay(40)
    ->withPlayCount(3)
    ->save($small);

$animation = AnimationBuilder::open($small)->toAnimation();
printf(
    "%s: %dx%d, %d frames, plays %d times, %d ms per play, frames held by %s\n",
    $small,
    $animation->dimensions->width,
    $animation->dimensions->height,
    $animation->frameCount(),
    $animation->playCount,
    $animation->durationMilliseconds(),
    $animation->frames[0]->image->driverName()->value,
);
