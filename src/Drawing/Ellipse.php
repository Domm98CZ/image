<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Drawing;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;

final readonly class Ellipse implements ShapeInterface
{
    public function __construct(
        public Point $center,
        // Full width and height (diameters), not radii.
        public Dimensions $size,
        public ?Color $fill = null,
        public ?Stroke $stroke = null,
    ) {
        Paint::assertSomething('Ellipse', $fill, $stroke);
    }
}
