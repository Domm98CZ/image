<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Geometry\Dimensions;

final readonly class Pad implements PrimitiveOperationInterface
{
    public function __construct(
        public int $left,
        public int $top,
        public int $right,
        public int $bottom,
        public Color $background = new Color(0, 0, 0, Color::TRANSPARENT),
    ) {
        ColorAdjustment::assertRange('Pad', 'left', $left, 0, Dimensions::MAX_SIDE);
        ColorAdjustment::assertRange('Pad', 'top', $top, 0, Dimensions::MAX_SIDE);
        ColorAdjustment::assertRange('Pad', 'right', $right, 0, Dimensions::MAX_SIDE);
        ColorAdjustment::assertRange('Pad', 'bottom', $bottom, 0, Dimensions::MAX_SIDE);
    }

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return new Dimensions($input->width + $this->left + $this->right, $input->height + $this->top + $this->bottom);
    }
}
