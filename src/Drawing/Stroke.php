<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Drawing;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Exception\InvalidDrawingException;

// Centred on the shape outline, like both GD and ImageMagick draw it.
final readonly class Stroke
{
    public const MAX_WIDTH = 1000;

    public function __construct(
        public Color $color,
        public int $width = 1,
    ) {
        if ($width < 1 || $width > self::MAX_WIDTH) {
            throw InvalidDrawingException::outOfRange('Stroke', 'width', $width, '1..' . self::MAX_WIDTH);
        }
    }
}
