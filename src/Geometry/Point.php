<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Geometry;

final readonly class Point
{
    public function __construct(
        public int $x,
        public int $y,
    ) {}

    public static function origin(): self
    {
        return new self(0, 0);
    }

    public function withX(int $x): self
    {
        return new self($x, $this->y);
    }

    public function withY(int $y): self
    {
        return new self($this->x, $y);
    }

    public function movedBy(int $deltaX, int $deltaY): self
    {
        return new self($this->x + $deltaX, $this->y + $deltaY);
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }
}
