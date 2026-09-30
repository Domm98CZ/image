<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Geometry;

use Domm98CZ\Image\Exception\InvalidOperationException;

final readonly class Rectangle
{
    public function __construct(
        public Point $origin,
        public Dimensions $dimensions,
    ) {}

    public static function covering(Dimensions $dimensions): self
    {
        return new self(Point::origin(), $dimensions);
    }

    public function withOrigin(Point $origin): self
    {
        return new self($origin, $this->dimensions);
    }

    public function withDimensions(Dimensions $dimensions): self
    {
        return new self($this->origin, $dimensions);
    }

    public function left(): int
    {
        return $this->origin->x;
    }

    public function top(): int
    {
        return $this->origin->y;
    }

    public function rightExclusive(): int
    {
        return $this->origin->x + $this->dimensions->width;
    }

    public function bottomExclusive(): int
    {
        return $this->origin->y + $this->dimensions->height;
    }

    public function contains(Point $point): bool
    {
        return $point->x >= $this->left() && $point->x < $this->rightExclusive()
            && $point->y >= $this->top() && $point->y < $this->bottomExclusive();
    }

    public function intersection(self $other): ?self
    {
        $left = max($this->left(), $other->left());
        $top = max($this->top(), $other->top());
        $right = min($this->rightExclusive(), $other->rightExclusive());
        $bottom = min($this->bottomExclusive(), $other->bottomExclusive());

        if ($right <= $left || $bottom <= $top) {
            return null;
        }

        return new self(new Point($left, $top), new Dimensions($right - $left, $bottom - $top));
    }

    public function isWithin(Dimensions $canvas): bool
    {
        return $this->left() >= 0 && $this->top() >= 0
            && $this->rightExclusive() <= $canvas->width && $this->bottomExclusive() <= $canvas->height;
    }

    public function assertWithin(Dimensions $canvas): void
    {
        if (!$this->isWithin($canvas)) {
            throw InvalidOperationException::areaOutsideImage($this, $canvas);
        }
    }

    public function equals(self $other): bool
    {
        return $this->origin->equals($other->origin) && $this->dimensions->equals($other->dimensions);
    }
}
