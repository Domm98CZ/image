<?php declare(strict_types=1);

// Which driver runs a step is the planner's call: drawing, for example, is native on Imagick and degraded on
// GD, so with both installed the text lands on Imagick. Force a driver to see what a GD-only or Imagick-only
// server would produce, or to pin a step while debugging. Forcing one that is not installed fails at the
// factory, before any image is touched.
// Run: php examples/forced-driver.php

require __DIR__ . '/../vendor/autoload.php';

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Exception\DriverNotAvailableException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\ImageFactory;

$font = new Font('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 32);

foreach (['auto' => Configuration::default(), 'gd' => Configuration::default()->withForcedDriver(DriverName::Gd), 'imagick' => Configuration::default()->withForcedDriver(DriverName::Imagick)] as $label => $configuration) {
    try {
        $image = (new ImageFactory($configuration))
            ->create(new Dimensions(320, 80), Color::white())
            ->draw(static fn(Canvas $c): Canvas => $c->text('Hello', new Point(16, 54), $font, Color::black()))
            ->toImage();
        printf("%-8s drawn on %s\n", $label, $image->driverName()->value);
    } catch (DriverNotAvailableException $exception) {
        printf("%-8s %s\n", $label, $exception->getMessage());
    }
}
