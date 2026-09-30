<?php declare(strict_types=1);

// With both extensions installed the planner prices every step per driver and picks the cheapest route,
// including the cost of handing pixels from one driver to the other. The prices are fitted to a CPU-only GD and
// ImageMagick 7; a machine whose drivers perform differently can pass its own. Here an image already on GD
// gets a PHP pixel filter: GD reads pixels through a PHP fallback (150 ns/px), Imagick natively (5 ns/px), and
// the hand-off costs 150 ns/px, so under the reference prices the step stays on GD. Priced with a cheap
// hand-off, the same step moves to Imagick.
// Run: php examples/custom-cost-model.php

require __DIR__ . '/../vendor/autoload.php';

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Exception\InvalidConfigurationException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Pipeline\CostModel;

// Rotates by 180 degrees in plain PHP: reverses the order of the 4-byte RGBA pixels.
$rotate = static fn(PixelBuffer $pixels): PixelBuffer => $pixels->withBytes(implode('', array_reverse(str_split($pixels->bytes, 4))));

$reference = new ImageFactory();
$calibrated = new ImageFactory(Configuration::default()->withCostModel(new CostModel(
    nativeNanosPerPixel: 1.0,
    phpFallbackNanosPerPixel: 150.0,
    transferNanosPerPixel: 20.0,
)));

$onGd = $reference->create(new Dimensions(640, 480), Color::rgb(90, 120, 150))->toImage();
printf("source image held by %s\n", $onGd->driverName()->value);

foreach (['reference prices' => $reference, 'calibrated prices' => $calibrated] as $label => $images) {
    $start = hrtime(true);
    $result = $images->from($onGd)->applyPixels($rotate)->toImage();
    printf("%-18s pixel step ran on %-8s %6.1f ms\n", $label, $result->driverName()->value, (hrtime(true) - $start) / 1e6);
}

// Only the ratios matter, but degradedNanosPerPixel must stay above the rest: a degraded step (GD ignoring an
// ICC profile, say) priced like a native one would let the planner trade output quality for speed.
try {
    new CostModel(degradedNanosPerPixel: 100.0);
} catch (InvalidConfigurationException $exception) {
    printf("refused: %s\n", $exception->getMessage());
}
