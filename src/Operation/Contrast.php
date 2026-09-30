<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

// Scales each channel around mid-grey by ((100 + level) / 100)^2: -100 flattens to grey, +100 quadruples; alpha untouched.
final readonly class Contrast implements PrimitiveOperationInterface
{
    public function __construct(
        public int $level,
    ) {
        ColorAdjustment::assertRange('Contrast', 'level', $level, -100, 100);
    }

    public function factor(): float
    {
        return (($this->level + 100) / 100) ** 2;
    }

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
