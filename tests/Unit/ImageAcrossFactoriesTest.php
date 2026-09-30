<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit;

use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Operation\FlipDirection;
use Domm98CZ\Image\Operation\Rotate;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Security\LimitType;
use Domm98CZ\Image\Security\LimitViolation;
use Domm98CZ\Image\Tests\Fake\FakeDriver;
use Domm98CZ\Image\Tests\Fake\FakeImageFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// An Image built by one ImageFactory and continued in another (ImageBuilder::from() does this on every call).
final class ImageAcrossFactoriesTest extends TestCase
{
    // Handles bind to a driver type, so the executor used to hand such an image over through a PNG for nothing.
    public function testAnImageFromAnotherFactoryStaysOnTheSameNamedDriverWithoutATransfer(): void
    {
        $gdA = new FakeDriver(DriverName::Gd);
        $gdB = new FakeDriver(DriverName::Gd);
        $b = FakeImageFactory::with($gdB);
        $image = FakeImageFactory::with($gdA)->create(new Dimensions(8, 6), Color::white())->toImage();
        // Handle ids count per driver; one handle on B first keeps the trace below unambiguous.
        $b->create(new Dimensions(1, 1))->toImage();
        $gdA->calls = [];
        $gdB->calls = [];

        $flipped = $b->from($image)->flip(FlipDirection::Horizontal)->toImage();

        self::assertSame([], $gdA->calls, 'no PNG encode on the driver the image came from');
        self::assertSame(['copy #1 -> #2', 'apply #2 flip horizontal'], $gdB->calls);
        self::assertSame($gdB, $flipped->driver());
    }

    public function testTheRegistryDriverIsMatchedByNameAmongSeveral(): void
    {
        $gdA = new FakeDriver(DriverName::Gd, unsupportedOperations: [Rotate::class]);
        $imagickA = new FakeDriver(DriverName::Imagick);
        $gdB = new FakeDriver(DriverName::Gd, unsupportedOperations: [Rotate::class]);
        $imagickB = new FakeDriver(DriverName::Imagick);
        $onImagick = FakeImageFactory::with($gdA, $imagickA)->create(new Dimensions(8, 6))->rotate(Angle::clockwise(90))->toImage();
        self::assertSame($imagickA, $onImagick->driver());

        $rotated = FakeImageFactory::with($gdB, $imagickB)->from($onImagick)->rotate(Angle::clockwise(90))->toImage();

        self::assertSame($imagickB, $rotated->driver());
        self::assertSame([], $gdB->calls);
        self::assertSame(['copy #1 -> #1', 'apply #1 rotate 90 #00000000'], $imagickB->calls);
        self::assertEquals(new Dimensions(8, 6), $rotated->dimensions);
    }

    public function testAnImageOnADriverTheFactoryLacksIsStillTransferred(): void
    {
        $imagick = new FakeDriver(DriverName::Imagick);
        $gd = new FakeDriver(DriverName::Gd);
        $image = FakeImageFactory::with($imagick)->create(new Dimensions(8, 6), Color::white())->toImage();
        $gdOnly = FakeImageFactory::configured(Configuration::default()->withForcedDriver(DriverName::Gd), $gd);
        $imagick->calls = [];

        $flipped = $gdOnly->from($image)->flip(FlipDirection::Horizontal)->toImage();

        self::assertSame(['encode #1 png'], $imagick->calls);
        self::assertSame(['decode png 8x6 -> #1', 'apply #1 flip horizontal'], $gd->calls);
        self::assertSame($gd, $flipped->driver());
    }

    public function testFromRefusesAnImageLargerThanThisFactoryAllowsBeforeAnyWork(): void
    {
        $driver = new FakeDriver();
        $image = FakeImageFactory::with($driver)->create(new Dimensions(10, 10))->toImage();
        $strict = FakeImageFactory::configured(new Configuration(Limits::default()->withMaxPixels(99)), $driver);
        $driver->calls = [];

        try {
            $strict->from($image);
            self::fail('Expected LimitExceededException.');
        } catch (LimitExceededException $exception) {
            self::assertEquals(new LimitViolation(LimitType::Pixels, 100, 99), $exception->violation);
        }
        self::assertSame([], $driver->calls);
    }

    /** @return iterable<string, array{Limits, int, LimitViolation}> */
    public static function animationLimits(): iterable
    {
        yield 'frames' => [Limits::default()->withMaxFrames(2), 3, new LimitViolation(LimitType::Frames, 3, 2)];
        yield 'frame pixels' => [Limits::default()->withMaxPixels(15), 1, new LimitViolation(LimitType::Pixels, 16, 15)];
        yield 'animation pixels' => [Limits::default()->withMaxAnimationPixels(47), 3, new LimitViolation(LimitType::AnimationPixels, 48, 47)];
    }

    #[DataProvider('animationLimits')]
    public function testAnimationRefusesFramesBeyondThisFactoryLimitsBeforeAnyWork(Limits $limits, int $frameCount, LimitViolation $expected): void
    {
        $driver = new FakeDriver();
        $frame = new Frame(FakeImageFactory::with($driver)->create(new Dimensions(4, 4))->toImage(), 10);
        $strict = FakeImageFactory::configured(new Configuration($limits), $driver);
        $driver->calls = [];

        try {
            $strict->animation(array_fill(0, $frameCount, $frame));
            self::fail('Expected LimitExceededException.');
        } catch (LimitExceededException $exception) {
            self::assertEquals($expected, $exception->violation);
        }
        self::assertSame([], $driver->calls);
    }
}
