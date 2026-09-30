<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Metadata;

use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Operation\Flip;
use Domm98CZ\Image\Operation\FlipDirection;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Operation\Rotate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrientationTest extends TestCase
{
    /** @return iterable<string, array{Orientation, list<PrimitiveOperationInterface>}> */
    public static function corrections(): iterable
    {
        yield '1 top left' => [Orientation::TopLeft, []];
        yield '2 top right' => [Orientation::TopRight, [new Flip(FlipDirection::Horizontal)]];
        yield '3 bottom right' => [Orientation::BottomRight, [new Rotate(Angle::clockwise(180))]];
        yield '4 bottom left' => [Orientation::BottomLeft, [new Flip(FlipDirection::Vertical)]];
        yield '5 left top' => [Orientation::LeftTop, [new Rotate(Angle::clockwise(90)), new Flip(FlipDirection::Horizontal)]];
        yield '6 right top' => [Orientation::RightTop, [new Rotate(Angle::clockwise(90))]];
        yield '7 right bottom' => [Orientation::RightBottom, [new Rotate(Angle::counterClockwise(90)), new Flip(FlipDirection::Horizontal)]];
        yield '8 left bottom' => [Orientation::LeftBottom, [new Rotate(Angle::counterClockwise(90))]];
    }

    /** @param list<PrimitiveOperationInterface> $expected */
    #[DataProvider('corrections')]
    public function testCorrections(Orientation $orientation, array $expected): void
    {
        self::assertEquals($expected, $orientation->corrections());
    }
}
