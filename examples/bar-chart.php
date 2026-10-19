<?php declare(strict_types=1);

// A grouped bar chart with soft drop shadows, built by composing pipeline steps: the shadow is its own layer (drawn,
// blurred, enlarged, then blended in with Multiply) that goes in between the grid and the bars, so it falls on the
// grid but never on the bars. Like the line chart it is drawn at three times its final size and shrunk with Lanczos,
// so the edges are anti-aliased on GD too; the translucent white highlight on each bar blends with the colour below it.
// Run: php examples/bar-chart.php [output-dir]

require __DIR__ . '/../vendor/autoload.php';

use Domm98CZ\Image\Blend\BlendMode;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\Stroke;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Operation\Interpolation;

$dir = $argv[1] ?? sys_get_temp_dir();
if (!is_dir($dir)) {
    fwrite(STDERR, "Not a directory: {$dir}\n");
    exit(1);
}

$images = new ImageFactory();
$scale = 3;
$width = 960;
$height = 540;

$categories = ['North', 'South', 'East', 'West', 'Central'];
$series = [
    ['name' => '2025', 'color' => Color::rgb(92, 107, 192), 'values' => [64, 48, 82, 37, 70]],
    ['name' => '2026', 'color' => Color::rgb(38, 166, 154), 'values' => [72, 61, 95, 44, 66]],
];

// Everything below is laid out in final-image pixels; $px() and $at() turn them into supersampled ones.
$left = 84.0;
$right = 920.0;
$top = 96.0;
$bottom = 468.0;
$yMax = 100;
$yStep = 20;
$barWidth = 54.0;
$barGap = 6.0;

$px = static fn(float $value): int => (int) round($value * $scale);
$at = static fn(float $x, float $y): Point => new Point($px($x), $px($y));
$yAt = static fn(float $value): float => $bottom - ($bottom - $top) * $value / $yMax;
$font = static fn(int $size, bool $bold = false): Font => new Font('/usr/share/fonts/truetype/dejavu/DejaVuSans' . ($bold ? '-Bold' : '') . '.ttf', $size * $scale);
$textWidth = static fn(string $text, Font $font): float => $images->text($text, $font, Color::black())->width() / $scale;

// Left edge of a bar: the series sit side by side, centred in their category's slot.
$barLeft = static function (int $category, int $line) use ($left, $right, $categories, $series, $barWidth, $barGap): float {
    $slot = ($right - $left) / count($categories);
    $group = count($series) * $barWidth + (count($series) - 1) * $barGap;

    return $left + $slot * $category + ($slot - $group) / 2 + $line * ($barWidth + $barGap);
};

$ink = Color::rgb(30, 30, 30);
$muted = Color::rgb(95, 95, 95);

$backdrop = static function (Canvas $c) use ($series, $left, $right, $top, $bottom, $yMax, $yStep, $scale, $px, $at, $yAt, $font, $textWidth, $ink, $muted): Canvas {
    $c = $c->text('Revenue by region', $at($left, 52), $font(24, true), $ink);

    $cursor = $right;
    foreach (array_reverse($series) as $line) {
        $cursor -= $textWidth($line['name'], $font(14));
        $c = $c
            ->text($line['name'], $at($cursor, 51), $font(14), $ink)
            ->rectangle(new Rectangle($at($cursor - 22, 40), new Dimensions($px(14), $px(14))), $line['color']);
        $cursor -= 46;
    }

    $axisTitle = 'Thousand EUR';
    $c = $c->text($axisTitle, $at(26, ($top + $bottom) / 2 + $textWidth($axisTitle, $font(14)) / 2), $font(14), $muted, Angle::counterClockwise(90));

    for ($tick = 0; $tick <= $yMax; $tick += $yStep) {
        $y = $yAt($tick);
        $c = $c
            ->line($at($left, $y), $at($right, $y), $tick === 0 ? new Stroke(Color::rgb(90, 90, 90), 2 * $scale) : new Stroke(Color::rgba(0, 0, 0, 0.10), $scale))
            ->text((string) $tick, $at($left - 12 - $textWidth((string) $tick, $font(13)), $y + 5), $font(13), $muted);
    }

    return $c;
};

$barBox = static function (int $category, int $line) use ($series, $barLeft, $barWidth, $bottom, $yAt, $px): Rectangle {
    $x = $barLeft($category, $line);
    $topEdge = $px($yAt($series[$line]['values'][$category]));

    return new Rectangle(new Point($px($x), $topEdge), new Dimensions($px($x + $barWidth) - $px($x), $px($bottom) - $topEdge));
};

$bars = static function (Canvas $c) use ($series, $categories, $barBox, $scale, $px, $at, $yAt, $font, $textWidth, $barLeft, $barWidth, $height, $left, $right, $bottom, $ink): Canvas {
    // The floor: the shadow must not fall below the axis, so it is covered before the category names are written.
    $c = $c->rectangle(new Rectangle($at($left, $bottom + 1), new Dimensions($px($right - $left), $px($height - $bottom - 1))), Color::white());
    foreach ($categories as $category => $name) {
        $groupLeft = $barLeft($category, 0);
        $groupRight = $barLeft($category, count($series) - 1) + $barWidth;
        $c = $c->text($name, $at(($groupLeft + $groupRight) / 2 - $textWidth($name, $font(14)) / 2, $bottom + 28), $font(14), $ink);
        foreach ($series as $line => $current) {
            $box = $barBox($category, $line);
            $value = $current['values'][$category];
            $label = (string) $value;
            $labelX = $barLeft($category, $line) + ($barWidth - $textWidth($label, $font(13, true))) / 2;
            $c = $c
                ->rectangle($box, $current['color'])
                ->rectangle(new Rectangle($box->origin, new Dimensions(10 * $scale, $box->dimensions->height)), Color::rgba(255, 255, 255, 0.18))
                ->text($label, $at($labelX, $yAt($value) - 9), $font(13, true), Color::rgb(30, 30, 30));
        }
    }

    return $c;
};

// The shadow is an opaque layer, light grey on white, multiplied in so it darkens whatever lies below it. It is made
// at final size: a blur this soft gains nothing from supersampling, and it is far cheaper.
$shadowOffset = 4;
$shadow = $images->create(new Dimensions($width, $height), Color::white())
    ->draw(static function (Canvas $c) use ($categories, $series, $barLeft, $barWidth, $bottom, $yAt, $shadowOffset): Canvas {
        foreach ($categories as $category => $name) {
            foreach ($series as $line => $current) {
                $topEdge = (int) round($yAt($current['values'][$category])) + $shadowOffset;
                $c = $c->rectangle(
                    new Rectangle(new Point((int) round($barLeft($category, $line)) + $shadowOffset, $topEdge), new Dimensions((int) $barWidth, (int) round($bottom) - $topEdge)),
                    Color::rgb(150, 150, 150),
                );
            }
        }

        return $c;
    })
    ->blur(5.0)
    ->resize(new Dimensions($width * $scale, $height * $scale), Interpolation::Bilinear)
    ->toImage();

$image = $images->create(new Dimensions($width * $scale, $height * $scale), Color::white())
    ->draw($backdrop)
    ->paste($shadow, new Point(0, 0), 1.0, BlendMode::Multiply)
    ->draw($bars)
    ->resize(new Dimensions($width, $height), Interpolation::Lanczos)
    ->toImage();

$path = $dir . '/bar-chart.png';
$png = $images->from($image)->save($path, new PngOutput());

$shaded = $image->colorAt(new Point((int) round($barLeft(2, 1) + $barWidth) + 2, (int) round($bottom) - 40));
printf("drawn at %dx and shrunk to %dx%d on %s, %d bytes written to %s\n", $scale, $image->width(), $image->height(), $image->driverName()->value, $png->byteCount(), $path);
printf("white under the soft shadow beside the tallest bar became rgb(%d, %d, %d)\n", $shaded->red, $shaded->green, $shaded->blue);
