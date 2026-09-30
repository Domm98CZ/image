<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Geometry\Dimensions;

// Blends every pixel toward the tint: value' = value * (1 - strength) + tint * strength; alpha untouched.
final readonly class Colorize implements PrimitiveOperationInterface
{
    public function __construct(
        public Color $tint,
        public float $strength = 0.5,
    ) {
        ColorAdjustment::assertRange('Colorize', 'strength', $strength, 0, 1);
    }

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
