<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

interface PrimitiveOperationInterface extends OperationInterface
{
    public function resultingDimensions(Dimensions $input): Dimensions;
}
