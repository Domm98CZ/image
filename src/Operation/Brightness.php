<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

// Adds level% of full scale to every color channel (clamped); alpha untouched.
final readonly class Brightness implements PrimitiveOperationInterface
{
    public function __construct(
        public int $level,
    ) {
        ColorAdjustment::assertRange('Brightness', 'level', $level, -100, 100);
    }

    public function offset(): int
    {
        return (int) round($this->level * 255 / 100);
    }

    public function resultingDimensions(Dimensions $input): Dimensions
    {
        return $input;
    }
}
