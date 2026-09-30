<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

final readonly class Flip implements PrimitiveOperationInterface
{
    public function __construct(
        public FlipDirection $direction,
    ) {}

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
