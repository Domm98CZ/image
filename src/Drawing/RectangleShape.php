<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Drawing;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Geometry\Rectangle;

final readonly class RectangleShape implements ShapeInterface
{
    public function __construct(
        public Rectangle $area,
        public ?Color $fill = null,
        public ?Stroke $stroke = null,
    ) {
        Paint::assertSomething('Rectangle', $fill, $stroke);
    }
}
