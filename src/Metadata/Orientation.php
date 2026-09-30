<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Metadata;

use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Operation\Flip;
use Domm98CZ\Image\Operation\FlipDirection;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Operation\Rotate;

// EXIF orientation tag values: where the stored image's first row and column should be displayed.
enum Orientation: int
{
    case TopLeft = 1;
    case TopRight = 2;
    case BottomRight = 3;
    case BottomLeft = 4;
    case LeftTop = 5;
    case RightTop = 6;
    case RightBottom = 7;
    case LeftBottom = 8;

    /** @return list<PrimitiveOperationInterface> */
    public function corrections(): array
    {
        return match ($this) {
            self::TopLeft => [],
            self::TopRight => [new Flip(FlipDirection::Horizontal)],
            self::BottomRight => [new Rotate(Angle::clockwise(180))],
            self::BottomLeft => [new Flip(FlipDirection::Vertical)],
            self::LeftTop => [new Rotate(Angle::clockwise(90)), new Flip(FlipDirection::Horizontal)],
            self::RightTop => [new Rotate(Angle::clockwise(90))],
            self::RightBottom => [new Rotate(Angle::counterClockwise(90)), new Flip(FlipDirection::Horizontal)],
            self::LeftBottom => [new Rotate(Angle::counterClockwise(90))],
        };
    }
}
