<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;

final readonly class Rotate implements PrimitiveOperationInterface
{
    public function __construct(
        public Angle $angle,
        // Fills the corners uncovered by a non right-angle rotation.
        public Color $background = new Color(0, 0, 0, Color::TRANSPARENT),
    ) {}

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        $degrees = $this->angle->clockwiseDegrees;
        if ($degrees === 90.0 || $degrees === 270.0) {
            return $input->swapped();
        }
        if ($this->angle->isRightAngleMultiple()) {
            return $input;
        }
        $cos = abs(cos($this->angle->radians()));
        $sin = abs(sin($this->angle->radians()));

        return new Dimensions(
            max(1, (int) round($input->width * $cos + $input->height * $sin)),
            max(1, (int) round($input->width * $sin + $input->height * $cos)),
        );
    }
}
