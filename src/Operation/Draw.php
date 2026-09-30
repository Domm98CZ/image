<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Drawing\ShapeInterface;
use Domm98CZ\Image\Geometry\Dimensions;

// Shapes are painted in order with normal alpha compositing; anything outside the image is clipped.
final readonly class Draw implements PrimitiveOperationInterface
{
    /** @param list<ShapeInterface> $shapes */
    public function __construct(
        public array $shapes,
    ) {}

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
