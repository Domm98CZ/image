<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

// Unsharp mask with a small (3x3, sigma ~0.7) Gaussian: value' = value + amount * (value - blurred).
final readonly class Sharpen implements PrimitiveOperationInterface
{
    public function __construct(
        public float $amount = 1.0,
    ) {
        ColorAdjustment::assertRange('Sharpen', 'amount', $amount, 0.01, 5);
    }

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
