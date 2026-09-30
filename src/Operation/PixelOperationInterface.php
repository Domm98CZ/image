<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;

// Runs in PHP over a PixelBuffer read from (and written back to) whichever driver holds the image.
interface PixelOperationInterface extends OperationInterface
{
    public function area(Dimensions $image): Rectangle;

    public function apply(PixelBuffer $pixels): PixelBuffer;
}
