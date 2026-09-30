<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Geometry;

use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use PHPUnit\Framework\TestCase;

final class RectangleTest extends TestCase
{
    public function testEdgesUseExclusiveRightAndBottom(): void
    {
        $rectangle = new Rectangle(new Point(10, 20), new Dimensions(30, 40));

        self::assertSame(10, $rectangle->left());
        self::assertSame(20, $rectangle->top());
        self::assertSame(40, $rectangle->rightExclusive());
        self::assertSame(60, $rectangle->bottomExclusive());
        self::assertTrue($rectangle->contains(new Point(39, 59)));
        self::assertFalse($rectangle->contains(new Point(40, 59)));
        self::assertFalse($rectangle->contains(new Point(9, 20)));
    }

    public function testIntersection(): void
    {
        $a = new Rectangle(new Point(0, 0), new Dimensions(100, 100));
        $b = new Rectangle(new Point(50, 80), new Dimensions(100, 100));

        $overlap = $a->intersection($b);

        self::assertNotNull($overlap);
        self::assertTrue($overlap->equals(new Rectangle(new Point(50, 80), new Dimensions(50, 20))));
    }

    public function testTouchingRectanglesDoNotIntersect(): void
    {
        $a = new Rectangle(new Point(0, 0), new Dimensions(10, 10));
        $b = new Rectangle(new Point(10, 0), new Dimensions(10, 10));

        self::assertNull($a->intersection($b));
    }

    public function testIsWithinCanvas(): void
    {
        $canvas = new Dimensions(100, 50);

        self::assertTrue(Rectangle::covering($canvas)->isWithin($canvas));
        self::assertFalse((new Rectangle(new Point(1, 0), $canvas))->isWithin($canvas));
        self::assertFalse((new Rectangle(new Point(-1, 0), new Dimensions(10, 10)))->isWithin($canvas));
    }

    public function testAssertWithinRejectsAreasLeavingTheCanvas(): void
    {
        $canvas = new Dimensions(100, 50);
        Rectangle::covering($canvas)->assertWithin($canvas);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('Pixel area 100x50 at 1,0 is not fully inside the 100x50 image.');

        (new Rectangle(new Point(1, 0), $canvas))->assertWithin($canvas);
    }

    public function testWithersReturnNewInstances(): void
    {
        $rectangle = Rectangle::covering(new Dimensions(10, 10));

        self::assertTrue($rectangle->withOrigin(new Point(5, 5))->origin->equals(new Point(5, 5)));
        self::assertTrue($rectangle->withDimensions(new Dimensions(3, 4))->dimensions->equals(new Dimensions(3, 4)));
        self::assertTrue($rectangle->origin->equals(Point::origin()));
    }
}
