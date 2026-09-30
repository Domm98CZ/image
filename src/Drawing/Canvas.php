<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Drawing;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;

// Immutable collector: every call returns a new Canvas; nothing is painted until the pipeline runs.
final readonly class Canvas
{
    /** @param list<ShapeInterface> $shapes */
    public function __construct(
        public array $shapes = [],
    ) {}

    public function add(ShapeInterface $shape): self
    {
        return new self([...$this->shapes, $shape]);
    }

    public function line(Point $from, Point $to, Stroke $stroke): self
    {
        return $this->add(new Line($from, $to, $stroke));
    }

    public function rectangle(Rectangle $area, ?Color $fill = null, ?Stroke $stroke = null): self
    {
        return $this->add(new RectangleShape($area, $fill, $stroke));
    }

    public function ellipse(Point $center, Dimensions $size, ?Color $fill = null, ?Stroke $stroke = null): self
    {
        return $this->add(new Ellipse($center, $size, $fill, $stroke));
    }

    /** @param list<Point> $points */
    public function polygon(array $points, ?Color $fill = null, ?Stroke $stroke = null): self
    {
        return $this->add(new Polygon($points, $fill, $stroke));
    }

    public function text(string $text, Point $baseline, Font $font, Color $color, ?Angle $angle = null): self
    {
        return $this->add(new Text($text, $baseline, $font, $color, $angle));
    }
}
