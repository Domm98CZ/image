<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

// Luma with Rec. 601 weights (0.299 R + 0.587 G + 0.114 B) on stored sRGB values; alpha untouched.
final readonly class Grayscale implements PrimitiveOperationInterface
{
    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
