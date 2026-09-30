<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Geometry;

final readonly class Position
{
    public function __construct(
        public Anchor $anchor,
        public int $offsetX = 0,
        public int $offsetY = 0,
    ) {}

    public static function at(Point $point): self
    {
        return new self(Anchor::TopLeft, $point->x, $point->y);
    }

    public static function anchored(Anchor $anchor): self
    {
        return new self($anchor);
    }

    public static function inset(Anchor $anchor, int $margin): self
    {
        return new self($anchor, -$anchor->horizontal() * $margin, -$anchor->vertical() * $margin);
    }

    public function resolve(Dimensions $canvas, Dimensions $item): Point
    {
        return $this->anchor->placeWithin($canvas, $item)->movedBy($this->offsetX, $this->offsetY);
    }
}
