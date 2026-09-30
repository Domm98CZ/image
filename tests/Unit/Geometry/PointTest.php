<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Geometry;

use Domm98CZ\Image\Geometry\Point;
use PHPUnit\Framework\TestCase;

final class PointTest extends TestCase
{
    public function testAllowsNegativeCoordinatesForOffCanvasPlacement(): void
    {
        $point = new Point(-5, -10);

        self::assertSame(-5, $point->x);
        self::assertSame(-10, $point->y);
    }

    public function testMovesAndWithersReturnNewInstances(): void
    {
        $point = new Point(3, 4);

        self::assertTrue($point->movedBy(2, -4)->equals(new Point(5, 0)));
        self::assertTrue($point->withX(9)->equals(new Point(9, 4)));
        self::assertTrue($point->withY(9)->equals(new Point(3, 9)));
        self::assertTrue($point->equals(new Point(3, 4)));
        self::assertTrue(Point::origin()->equals(new Point(0, 0)));
    }
}
