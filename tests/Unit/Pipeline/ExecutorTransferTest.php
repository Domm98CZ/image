<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Pipeline;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Operation\Rotate;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Tests\Fake\FakeDriver;
use Domm98CZ\Image\Tests\Fake\FakeImageFactory;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\TestCase;

final class ExecutorTransferTest extends TestCase
{
    public function testTransfersThroughLosslessPngWhenAStepNeedsAnotherDriver(): void
    {
        $gd = new FakeDriver(DriverName::Gd, unsupportedOperations: [Rotate::class]);
        $imagick = new FakeDriver(DriverName::Imagick, unencodable: [FormatName::Avif]);

        $encoded = FakeImageFactory::with($gd, $imagick)
            ->openBytes(ImageBytes::jpeg(40, 30))
            ->scale(0.5)
            ->rotate(Angle::clockwise(90))
            ->encode(new AvifOutput());

        // One transfer beats two: decode and resize where the rotation can run, move once to the AVIF encoder.
        self::assertSame(FormatName::Avif, $encoded->format);
        self::assertSame([
            'decode jpeg 40x30 -> #1',
            'apply #1 resize 20x15',
            'apply #1 rotate 90 #00000000',
            'encode #1 png',
        ], $imagick->calls);
        self::assertSame([
            'decode png 15x20 -> #1',
            'encode #1 avif',
        ], $gd->calls);
    }

    public function testImageSourceIsNotCopiedWhenItsFirstStepRunsOnAnotherDriver(): void
    {
        $gd = new FakeDriver(DriverName::Gd, unsupportedOperations: [Rotate::class]);
        $imagick = new FakeDriver(DriverName::Imagick);
        $factory = FakeImageFactory::with($gd, $imagick);
        $base = $factory->create(new Dimensions(8, 6), Color::white())->toImage();
        $gd->calls = [];

        $rotated = $factory->from($base)->rotate(Angle::clockwise(90))->toImage();

        self::assertSame(['encode #1 png'], $gd->calls);
        self::assertSame(DriverName::Imagick, $rotated->driverName());
        self::assertEquals(new Dimensions(6, 8), $rotated->dimensions);
    }

    // The input byte cap guards untrusted files; a hand-off PNG the library encoded itself is not one of them.
    public function testATransferIsNotSubjectToTheInputByteCap(): void
    {
        $gd = new FakeDriver(DriverName::Gd, unsupportedOperations: [Rotate::class]);
        $imagick = new FakeDriver(DriverName::Imagick);
        $factory = FakeImageFactory::configured(new Configuration(Limits::default()->withMaxInputBytes(1)), $gd, $imagick);

        $base = $factory->create(new Dimensions(8, 6), Color::white())->toImage();
        $gd->calls = [];

        $rotated = $factory->from($base)->rotate(Angle::clockwise(90))->toImage();

        self::assertSame(['encode #1 png'], $gd->calls);
        self::assertSame(['decode png 8x6 -> #1', 'apply #1 rotate 90 #00000000'], $imagick->calls);
        self::assertEquals(new Dimensions(6, 8), $rotated->dimensions);
    }

    public function testImagePixelsAreReadThroughItsDriver(): void
    {
        $driver = new FakeDriver(pixelColor: Color::fromHex('#abcdef'));
        $image = FakeImageFactory::with($driver)->create(new Dimensions(10, 10))->toImage();

        $all = $image->pixels();
        $region = $image->pixels(new Rectangle(new Point(2, 3), new Dimensions(4, 5)));

        self::assertEquals(new Dimensions(10, 10), $all->dimensions);
        self::assertSame('#abcdef', $region->colorAt(new Point(3, 4))->toHex());
        self::assertSame(['create 10x10 #00000000 -> #1', 'read #1 10x10+0+0', 'read #1 4x5+2+3'], $driver->calls);
    }
}
