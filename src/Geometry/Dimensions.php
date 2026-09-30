<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Geometry;

use Domm98CZ\Image\Exception\InvalidDimensionsException;
use Domm98CZ\Image\Exception\InvalidOperationException;

final readonly class Dimensions
{
    // Keeps width * height * 8 bytes (16-bit RGBA, Imagick Q16) inside a 64-bit int.
    public const MAX_SIDE = 1_000_000_000;

    public function __construct(
        public int $width,
        public int $height,
    ) {
        if ($width < 1 || $height < 1) {
            throw InvalidDimensionsException::notPositive($width, $height);
        }
        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw InvalidDimensionsException::tooLarge($width, $height, self::MAX_SIDE);
        }
    }

    public function withWidth(int $width): self
    {
        return new self($width, $this->height);
    }

    public function withHeight(int $height): self
    {
        return new self($this->width, $height);
    }

    public function pixelCount(): int
    {
        return $this->width * $this->height;
    }

    public function aspectRatio(): float
    {
        return $this->width / $this->height;
    }

    public function isLandscape(): bool
    {
        return $this->width > $this->height;
    }

    public function isPortrait(): bool
    {
        return $this->height > $this->width;
    }

    public function isSquare(): bool
    {
        return $this->width === $this->height;
    }

    public function swapped(): self
    {
        return new self($this->height, $this->width);
    }

    public function scaledBy(float $factor): self
    {
        if (!is_finite($factor) || $factor <= 0.0) {
            throw InvalidDimensionsException::invalidScaleFactor($factor);
        }

        return new self(self::roundSide($this->width * $factor), self::roundSide($this->height * $factor));
    }

    public function scaledToWidth(int $width): self
    {
        return new self($width, self::roundSide($width / $this->aspectRatio()));
    }

    public function scaledToHeight(int $height): self
    {
        return new self(self::roundSide($height * $this->aspectRatio()), $height);
    }

    public function fitInside(self $box): self
    {
        return $this->aspectRatio() >= $box->aspectRatio()
            ? $this->scaledToWidth($box->width)
            : $this->scaledToHeight($box->height);
    }

    public function fitOutside(self $box): self
    {
        return $this->aspectRatio() >= $box->aspectRatio()
            ? $this->scaledToHeight($box->height)
            : $this->scaledToWidth($box->width);
    }

    public function contains(self $other): bool
    {
        return $other->width <= $this->width && $other->height <= $this->height;
    }

    public function assertContainsPoint(Point $point): void
    {
        if (!Rectangle::covering($this)->contains($point)) {
            throw InvalidOperationException::pointOutsideImage($point, $this);
        }
    }

    public function equals(self $other): bool
    {
        return $this->width === $other->width && $this->height === $other->height;
    }

    private static function roundSide(float $side): int
    {
        if ($side > self::MAX_SIDE) {
            throw InvalidDimensionsException::sideTooLarge($side, self::MAX_SIDE);
        }

        return max(1, (int) round($side));
    }
}
