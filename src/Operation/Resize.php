<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

final readonly class Resize implements PrimitiveOperationInterface
{
    public function __construct(
        public Dimensions $target,
        public Interpolation $interpolation = Interpolation::Lanczos,
    ) {}

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $this->target;
    }
}
