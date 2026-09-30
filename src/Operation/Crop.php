<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;

final readonly class Crop implements PrimitiveOperationInterface
{
    public function __construct(
        public Rectangle $area,
    ) {}

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        if (!$this->area->isWithin($input)) {
            throw InvalidOperationException::cropOutsideImage($this->area, $input);
        }

        return $this->area->dimensions;
    }
}
