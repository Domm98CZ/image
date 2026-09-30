<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Geometry;

use Domm98CZ\Image\Exception\InvalidAngleException;

final readonly class Angle
{
    public float $clockwiseDegrees;

    private function __construct(float $clockwiseDegrees)
    {
        if (!is_finite($clockwiseDegrees)) {
            throw InvalidAngleException::notFinite($clockwiseDegrees);
        }
        $normalized = fmod($clockwiseDegrees, 360.0);
        if ($normalized < 0.0) {
            $normalized += 360.0;
        }
        // A tiny negative input rounds up to exactly 360.0; +0.0 folds -0.0 into one canonical zero.
        $this->clockwiseDegrees = $normalized >= 360.0 ? 0.0 : $normalized + 0.0;
    }

    public static function clockwise(float $degrees): self
    {
        return new self($degrees);
    }

    public static function counterClockwise(float $degrees): self
    {
        return new self(-$degrees);
    }

    public function radians(): float
    {
        return deg2rad($this->clockwiseDegrees);
    }

    public function isZero(): bool
    {
        return $this->clockwiseDegrees === 0.0;
    }

    public function isRightAngleMultiple(): bool
    {
        return fmod($this->clockwiseDegrees, 90.0) === 0.0;
    }

    public function equals(self $other): bool
    {
        return $this->clockwiseDegrees === $other->clockwiseDegrees;
    }
}
