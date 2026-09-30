<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Operation;

use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Operation\Crop;
use Domm98CZ\Image\Operation\Flip;
use Domm98CZ\Image\Operation\FlipDirection;
use Domm98CZ\Image\Operation\Interpolation;
use Domm98CZ\Image\Operation\Resize;
use Domm98CZ\Image\Operation\Rotate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PrimitiveOperationTest extends TestCase
{
    public function testResizeYieldsTarget(): void
    {
        self::assertEquals(new Dimensions(10, 20), (new Resize(new Dimensions(10, 20)))->resultingDimensions(new Dimensions(400, 300)));
    }

    public function testResizeDefaultsToLanczosInterpolation(): void
    {
        self::assertSame(Interpolation::Lanczos, (new Resize(new Dimensions(10, 20)))->interpolation);
    }

    public function testCropYieldsAreaInsideImage(): void
    {
        $crop = new Crop(new Rectangle(new Point(100, 50), new Dimensions(300, 250)));

        self::assertEquals(new Dimensions(300, 250), $crop->resultingDimensions(new Dimensions(400, 300)));
    }

    public function testCropOutsideImageIsRejected(): void
    {
        $this->expectException(InvalidOperationException::class);

        (new Crop(new Rectangle(new Point(101, 50), new Dimensions(300, 250))))->resultingDimensions(new Dimensions(400, 300));
    }

    public function testFlipKeepsDimensions(): void
    {
        self::assertEquals(new Dimensions(4, 3), (new Flip(FlipDirection::Both))->resultingDimensions(new Dimensions(4, 3)));
    }

    /** @return iterable<string, array{Angle, Dimensions}> */
    public static function rotations(): iterable
    {
        yield 'quarter clockwise' => [Angle::clockwise(90), new Dimensions(300, 400)];
        yield 'forty five' => [Angle::clockwise(45), new Dimensions(495, 495)];
    }

    #[DataProvider('rotations')]
    public function testRotateYieldsBoundingBox(Angle $angle, Dimensions $expected): void
    {
        $result = (new Rotate($angle))->resultingDimensions(new Dimensions(400, 300));

        self::assertEquals($expected, $result, sprintf('got %dx%d', $result->width, $result->height));
    }

    public function testRotateDefaultsToTransparentBackground(): void
    {
        self::assertTrue((new Rotate(Angle::clockwise(10)))->background->isFullyTransparent());
    }
}
