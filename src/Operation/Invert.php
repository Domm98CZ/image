<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

// Each color channel becomes 255 - value; alpha untouched.
final readonly class Invert implements PrimitiveOperationInterface
{
    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
