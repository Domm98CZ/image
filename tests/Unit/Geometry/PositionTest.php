<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Geometry;

use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Position;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PositionTest extends TestCase
{
    /** @return iterable<string, array{Anchor, Point}> */
    public static function anchors(): iterable
    {
        yield 'top left' => [Anchor::TopLeft, new Point(0, 0)];
        yield 'top' => [Anchor::Top, new Point(40, 0)];
        yield 'top right' => [Anchor::TopRight, new Point(80, 0)];
        yield 'left' => [Anchor::Left, new Point(0, 20)];
        yield 'center' => [Anchor::Center, new Point(40, 20)];
        yield 'right' => [Anchor::Right, new Point(80, 20)];
        yield 'bottom left' => [Anchor::BottomLeft, new Point(0, 40)];
        yield 'bottom' => [Anchor::Bottom, new Point(40, 40)];
        yield 'bottom right' => [Anchor::BottomRight, new Point(80, 40)];
    }

    #[DataProvider('anchors')]
    public function testAnchorPlacesItemWithinCanvas(Anchor $anchor, Point $expected): void
    {
        $placed = Position::anchored($anchor)->resolve(new Dimensions(100, 50), new Dimensions(20, 10));

        self::assertTrue($placed->equals($expected), sprintf('got %d,%d', $placed->x, $placed->y));
    }

    public function testInsetMovesAwayFromAnchoredEdges(): void
    {
        $canvas = new Dimensions(100, 50);
        $item = new Dimensions(20, 10);

        self::assertTrue(Position::inset(Anchor::BottomRight, 5)->resolve($canvas, $item)->equals(new Point(75, 35)));
        self::assertTrue(Position::inset(Anchor::TopLeft, 5)->resolve($canvas, $item)->equals(new Point(5, 5)));
        self::assertTrue(Position::inset(Anchor::Center, 5)->resolve($canvas, $item)->equals(new Point(40, 20)));
    }

    public function testAtIsAbsoluteFromTopLeft(): void
    {
        $placed = Position::at(new Point(7, -3))->resolve(new Dimensions(100, 50), new Dimensions(20, 10));

        self::assertTrue($placed->equals(new Point(7, -3)));
    }

    public function testItemLargerThanCanvasGetsNegativeOrigin(): void
    {
        $placed = Position::anchored(Anchor::Center)->resolve(new Dimensions(100, 100), new Dimensions(200, 120));

        self::assertTrue($placed->equals(new Point(-50, -10)));
    }
}
