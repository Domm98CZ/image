<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Blend\BlendMode;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Image;

// Paints $overlay, scaled to $placement, over the image; the part outside the image is clipped.
final readonly class Composite implements PrimitiveOperationInterface
{
    public function __construct(
        public Image $overlay,
        public Rectangle $placement,
        public float $opacity = 1.0,
        public BlendMode $mode = BlendMode::Normal,
    ) {
        ColorAdjustment::assertRange('Composite', 'opacity', $opacity, 0, 1);
    }

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
