<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Drawing;

use Domm98CZ\Image\Geometry\Point;

final readonly class Line implements ShapeInterface
{
    public function __construct(
        public Point $from,
        public Point $to,
        public Stroke $stroke,
    ) {}
}
