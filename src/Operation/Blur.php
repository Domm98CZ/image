<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

// Gaussian blur; sigma is capped because blur cost grows with it and it often comes from request parameters.
final readonly class Blur implements PrimitiveOperationInterface
{
    public const MAX_SIGMA = 50.0;

    public function __construct(
        public float $sigma,
    ) {
        ColorAdjustment::assertRange('Blur', 'sigma', $sigma, 0.1, self::MAX_SIGMA);
    }

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
