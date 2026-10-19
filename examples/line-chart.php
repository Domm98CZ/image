<?php declare(strict_types=1);

// A two-series line chart with translucent area fills, built from nothing but Canvas primitives (line, polygon,
// ellipse, rectangle, text). The chart is drawn at three times its final size and shrunk with Lanczos, so the edges
// come out anti-aliased even on GD, whose thick strokes and filled shapes are otherwise jagged. Where the two areas
// overlap the translucent fills blend, and the y-axis title is the same text drawn rotated.
// Run: php examples/line-chart.php [output-dir]

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

$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$series = [
    ['name' => 'Desktop', 'color' => Color::rgb(66, 133, 244), 'values' => [42, 48, 45, 58, 63, 60, 72, 78, 74, 85, 88, 94]],
    ['name' => 'Mobile', 'color' => Color::rgb(244, 120, 50), 'values' => [18, 24, 30, 33, 41, 52, 49, 61, 68, 66, 79, 84]],
];

// Everything below is laid out in final-image pixels; $px() and $at() turn them into supersampled ones.
$left = 84.0;
$right = 920.0;
$top = 96.0;
$bottom = 468.0;
$yMax = 100;
$yStep = 20;

$px = static fn(float $value): int => (int) round($value * $scale);
$at = static fn(float $x, float $y): Point => new Point($px($x), $px($y));
$xAt = static fn(int $index): float => $left + ($right - $left) * $index / (count($months) - 1);
$yAt = static fn(float $value): float => $bottom - ($bottom - $top) * $value / $yMax;
$font = static fn(int $size, bool $bold = false): Font => new Font('/usr/share/fonts/truetype/dejavu/DejaVuSans' . ($bold ? '-Bold' : '') . '.ttf', $size * $scale);
$textWidth = static fn(string $text, Font $font): float => $images->text($text, $font, Color::black())->width() / $scale;

$ink = Color::rgb(30, 30, 30);
$muted = Color::rgb(95, 95, 95);

$chart = static function (Canvas $c) use ($series, $months, $left, $right, $top, $bottom, $yMax, $yStep, $scale, $px, $at, $xAt, $yAt, $font, $textWidth, $ink, $muted): Canvas {
    $c = $c->text('Sessions per month', $at($left, 52), $font(24, true), $ink);

    $cursor = $right;
    foreach (array_reverse($series) as $line) {
        $cursor -= $textWidth($line['name'], $font(14));
        $c = $c
            ->text($line['name'], $at($cursor, 51), $font(14), $ink)
            ->rectangle(new Rectangle($at($cursor - 22, 40), new Dimensions($px(14), $px(14))), $line['color']);
        $cursor -= 46;
    }

    $axisTitle = 'Thousands of sessions';
    $c = $c->text($axisTitle, $at(26, ($top + $bottom) / 2 + $textWidth($axisTitle, $font(14)) / 2), $font(14), $muted, Angle::counterClockwise(90));

    for ($tick = 0; $tick <= $yMax; $tick += $yStep) {
        $y = $yAt($tick);
        $c = $c
            ->line($at($left, $y), $at($right, $y), $tick === 0 ? new Stroke(Color::rgb(90, 90, 90), 2 * $scale) : new Stroke(Color::rgba(0, 0, 0, 0.10), $scale))
            ->text((string) $tick, $at($left - 12 - $textWidth((string) $tick, $font(13)), $y + 5), $font(13), $muted);
    }
    foreach ($months as $index => $month) {
        $c = $c->text($month, $at($xAt($index) - $textWidth($month, $font(13)) / 2, $bottom + 26), $font(13), $muted);
    }

    foreach ($series as $line) {
        $points = [];
        foreach ($line['values'] as $index => $value) {
            $points[] = $at($xAt($index), $yAt($value));
        }
        $last = count($points) - 1;
        $c = $c->polygon([...$points, $at($xAt($last), $yAt(0)), $at($xAt(0), $yAt(0))], $line['color']->withOpacity(0.16));
    }

    foreach ($series as $line) {
        $points = [];
        foreach ($line['values'] as $index => $value) {
            $points[] = $at($xAt($index), $yAt($value));
        }
        for ($index = 1; $index < count($points); ++$index) {
            $c = $c->line($points[$index - 1], $points[$index], new Stroke($line['color'], 3 * $scale));
        }
        foreach ($points as $point) {
            $c = $c->ellipse($point, new Dimensions($px(11), $px(11)), Color::white(), new Stroke($line['color'], 2 * $scale));
        }
        $last = count($points) - 1;
        $label = (string) $line['values'][$last];
        $c = $c->text($label, $at($xAt($last) - $textWidth($label, $font(14, true)) / 2, $yAt($line['values'][$last]) - 14), $font(14, true), $line['color']);
    }

    return $c;
};

$image = $images->create(new Dimensions($width * $scale, $height * $scale), Color::white())
    ->draw($chart)
    ->resize(new Dimensions($width, $height), Interpolation::Lanczos)
    ->toImage();

$path = $dir . '/line-chart.png';
$png = $images->from($image)->save($path, new PngOutput());

$overlap = $image->colorAt(new Point((int) round($xAt(6)), (int) round($yAt(25))));
printf("drawn at %dx and shrunk to %dx%d on %s, %d bytes written to %s\n", $scale, $image->width(), $image->height(), $image->driverName()->value, $png->byteCount(), $path);
printf("where the two translucent areas overlap they blend: rgb(%d, %d, %d)\n", $overlap->red, $overlap->green, $overlap->blue);
