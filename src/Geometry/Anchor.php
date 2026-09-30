<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Geometry;

enum Anchor
{
    case TopLeft;
    case Top;
    case TopRight;
    case Left;
    case Center;
    case Right;
    case BottomLeft;
    case Bottom;
    case BottomRight;

    public function placeWithin(Dimensions $canvas, Dimensions $item): Point
    {
        return new Point(
            match ($this->horizontal()) {
                -1 => 0,
                0 => intdiv($canvas->width - $item->width, 2),
                1 => $canvas->width - $item->width,
            },
            match ($this->vertical()) {
                -1 => 0,
                0 => intdiv($canvas->height - $item->height, 2),
                1 => $canvas->height - $item->height,
            },
        );
    }

    /** @return -1|0|1 */
    public function horizontal(): int
    {
        return match ($this) {
            self::TopLeft, self::Left, self::BottomLeft => -1,
            self::Top, self::Center, self::Bottom => 0,
            self::TopRight, self::Right, self::BottomRight => 1,
        };
    }

    /** @return -1|0|1 */
    public function vertical(): int
    {
        return match ($this) {
            self::TopLeft, self::Top, self::TopRight => -1,
            self::Left, self::Center, self::Right => 0,
            self::BottomLeft, self::Bottom, self::BottomRight => 1,
        };
    }
}
