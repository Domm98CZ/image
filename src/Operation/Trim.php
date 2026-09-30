<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

final readonly class Trim implements PrimitiveOperationInterface
{
    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
