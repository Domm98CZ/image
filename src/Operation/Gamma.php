<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

// value' = 255 * (value / 255)^(1 / gamma): gamma > 1 brightens mid-tones; alpha untouched.
final readonly class Gamma implements PrimitiveOperationInterface
{
    public function __construct(
        public float $gamma,
    ) {
        ColorAdjustment::assertRange('Gamma', 'gamma', $gamma, 0.01, 10);
    }

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
