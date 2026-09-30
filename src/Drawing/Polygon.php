<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Drawing;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Exception\InvalidDrawingException;
use Domm98CZ\Image\Geometry\Point;

final readonly class Polygon implements ShapeInterface
{
    public const MAX_POINTS = 10_000;

    /** @param list<Point> $points */
    public function __construct(
        public array $points,
        public ?Color $fill = null,
        public ?Stroke $stroke = null,
    ) {
        if (count($points) < 3 || count($points) > self::MAX_POINTS) {
            throw InvalidDrawingException::outOfRange('Polygon', 'point count', count($points), '3..' . self::MAX_POINTS);
        }
        Paint::assertSomething('Polygon', $fill, $stroke);
    }
}
