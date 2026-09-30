<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Geometry;

use Domm98CZ\Image\Exception\InvalidDimensionsException;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DimensionsTest extends TestCase
{
    /** @return iterable<string, array{int, int}> */
    public static function nonPositiveSides(): iterable
    {
        yield 'zero width' => [0, 10];
        yield 'negative height' => [10, -5];
    }

    #[DataProvider('nonPositiveSides')]
    public function testRejectsNonPositiveSides(int $width, int $height): void
    {
        $this->expectException(InvalidDimensionsException::class);

        new Dimensions($width, $height);
    }

    public function testAssertContainsPointRejectsPointsOnOrBeyondTheEdge(): void
    {
        $dimensions = new Dimensions(10, 5);
        $dimensions->assertContainsPoint(new Point(9, 4));

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('Point 10,4 is outside the 10x5 image.');

        $dimensions->assertContainsPoint(new Point(10, 4));
    }

    public function testRejectsSidesBeyondRepresentableMaximum(): void
    {
        $this->expectException(InvalidDimensionsException::class);

        new Dimensions(Dimensions::MAX_SIDE + 1, 1);
    }

    public function testMaximumSidesDoNotOverflowSixteenBitRgbaByteCount(): void
    {
        $dimensions = new Dimensions(Dimensions::MAX_SIDE, Dimensions::MAX_SIDE);

        self::assertIsInt($dimensions->pixelCount() * 8);
    }

    public function testReportsPixelCountAndOrientation(): void
    {
        $landscape = new Dimensions(400, 300);

        self::assertSame(120_000, $landscape->pixelCount());
        self::assertTrue($landscape->isLandscape());
        self::assertFalse($landscape->isPortrait());
        self::assertTrue($landscape->swapped()->isPortrait());
        self::assertTrue((new Dimensions(5, 5))->isSquare());
    }

    public function testWithersReturnNewInstances(): void
    {
        $original = new Dimensions(400, 300);

        self::assertTrue($original->withWidth(10)->equals(new Dimensions(10, 300)));
        self::assertTrue($original->withHeight(20)->equals(new Dimensions(400, 20)));
        self::assertTrue($original->equals(new Dimensions(400, 300)));
    }

    public function testScaledByRoundsAndNeverCollapsesToZero(): void
    {
        self::assertTrue((new Dimensions(401, 301))->scaledBy(0.5)->equals(new Dimensions(201, 151)));
        self::assertTrue((new Dimensions(10, 10))->scaledBy(0.001)->equals(new Dimensions(1, 1)));
    }

    /** @return iterable<string, array{float}> */
    public static function invalidScaleFactors(): iterable
    {
        yield 'zero' => [0.0];
        yield 'nan' => [NAN];
    }

    #[DataProvider('invalidScaleFactors')]
    public function testScaledByRejectsInvalidFactors(float $factor): void
    {
        $this->expectException(InvalidDimensionsException::class);

        (new Dimensions(10, 10))->scaledBy($factor);
    }

    public function testScaledByRejectsResultBeyondMaximum(): void
    {
        $this->expectException(InvalidDimensionsException::class);

        (new Dimensions(10, 10))->scaledBy(1e12);
    }

    public function testScalesToWidthAndHeightKeepingAspectRatio(): void
    {
        $dimensions = new Dimensions(1600, 900);

        self::assertTrue($dimensions->scaledToWidth(800)->equals(new Dimensions(800, 450)));
        self::assertTrue($dimensions->scaledToHeight(90)->equals(new Dimensions(160, 90)));
    }

    /** @return iterable<string, array{Dimensions, Dimensions, Dimensions, Dimensions}> */
    public static function fitCases(): iterable
    {
        yield 'wider than box' => [new Dimensions(1600, 900), new Dimensions(400, 400), new Dimensions(400, 225), new Dimensions(711, 400)];
        yield 'taller than box' => [new Dimensions(900, 1600), new Dimensions(400, 400), new Dimensions(225, 400), new Dimensions(400, 711)];
        yield 'same ratio' => [new Dimensions(1000, 500), new Dimensions(200, 100), new Dimensions(200, 100), new Dimensions(200, 100)];
        yield 'upscales small image' => [new Dimensions(100, 50), new Dimensions(400, 400), new Dimensions(400, 200), new Dimensions(800, 400)];
    }

    #[DataProvider('fitCases')]
    public function testFitInsideAndOutside(Dimensions $image, Dimensions $box, Dimensions $inside, Dimensions $outside): void
    {
        $fittedInside = $image->fitInside($box);
        $fittedOutside = $image->fitOutside($box);

        self::assertTrue($fittedInside->equals($inside), sprintf('inside: got %dx%d', $fittedInside->width, $fittedInside->height));
        self::assertTrue($fittedOutside->equals($outside), sprintf('outside: got %dx%d', $fittedOutside->width, $fittedOutside->height));
        self::assertTrue($box->contains($fittedInside));
        self::assertTrue($fittedOutside->contains($box));
    }
}
