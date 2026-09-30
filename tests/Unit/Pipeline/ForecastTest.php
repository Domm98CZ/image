<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Pipeline;

use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Operation\AutoOrient;
use Domm98CZ\Image\Operation\Crop;
use Domm98CZ\Image\Operation\Resize;
use Domm98CZ\Image\Operation\Thumbnail;
use Domm98CZ\Image\Operation\Trim;
use Domm98CZ\Image\Pipeline\Forecast;
use PHPUnit\Framework\TestCase;

final class ForecastTest extends TestCase
{
    public function testStepPixelsAreTheLargerSideOfEachStep(): void
    {
        $forecast = Forecast::of(new Dimensions(100, 100), Orientation::TopLeft, [
            new Resize(new Dimensions(200, 200)),
            new Resize(new Dimensions(10, 10)),
            new Thumbnail(new Dimensions(5, 5), Anchor::Center),
        ]);

        self::assertSame(10_000, $forecast->initialPixels);
        self::assertSame([40_000, 40_000, 100], $forecast->stepPixels);
        self::assertSame(25, $forecast->finalPixels);
    }

    public function testAutoOrientUsesSourceOrientationOnce(): void
    {
        $forecast = Forecast::of(new Dimensions(40, 10), Orientation::RightTop, [new AutoOrient(), new AutoOrient(), new Crop(new Rectangle(new Point(0, 0), new Dimensions(10, 40)))]);

        self::assertSame(400, $forecast->finalPixels);
    }

    public function testInvalidStepsKeepThePreviousPredictionInsteadOfFailing(): void
    {
        $forecast = Forecast::of(new Dimensions(10, 10), Orientation::TopLeft, [new Crop(new Rectangle(new Point(5, 5), new Dimensions(10, 10)))]);

        self::assertSame([100], $forecast->stepPixels);
    }

    public function testTrimUsesItsConservativeInputDimensionsForFollowingSteps(): void
    {
        $forecast = Forecast::of(new Dimensions(100, 80), Orientation::TopLeft, [new Trim(), new Resize(new Dimensions(10, 10))]);

        self::assertSame([8_000, 8_000], $forecast->stepPixels);
        self::assertSame(100, $forecast->finalPixels);
    }
}
