<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration;

use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Driver\Gd\GdDriver;
use Domm98CZ\Image\Driver\Imagick\ImagickDriver;
use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\ImageBuilder;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Operation\FlipDirection;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Security\LimitType;
use Domm98CZ\Image\Security\LimitViolation;
use Domm98CZ\Image\Tests\Support\RecordingDriver;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// Images built by one ImageFactory and continued in another, with real drivers.
#[RequiresPhpExtension('gd')]
final class ImageAcrossFactoriesTest extends TestCase
{
    // ImageBuilder::from() builds a fresh factory, i.e. a fresh GdDriver; the image must still stay on GD.
    public function testAnImageFromAnotherFactoryIsNotHandedOverThroughPng(): void
    {
        $spy = new RecordingDriver(new GdDriver());
        $image = (new ImageFactory(Configuration::default(), new DriverRegistry([$spy])))
            ->create(new Dimensions(40, 20), Color::fromHex('#ff0000'))
            ->toImage();
        $spy->calls = [];

        $flipped = ImageBuilder::from($image)->flip(FlipDirection::Horizontal)->toImage();

        self::assertSame([], $spy->calls, 'the originating driver neither encodes nor is asked to copy');
        self::assertSame(DriverName::Gd, $flipped->driverName());
        self::assertNotSame($spy, $flipped->driver());
        self::assertSame('#ff0000', $flipped->colorAt(new Point(0, 0))->toHex());
        self::assertSame('#ff0000', $image->colorAt(new Point(39, 19))->toHex());
    }

    // The README scenario: a factory allowing 80 MP hands a 60 MP image to the default limits (50 MP). It has to fail
    // at from(), with the pixel limit named, rather than later inside a transfer with an input-size error.
    public function testAnImageBeyondTheDefaultLimitsIsRefusedAtFrom(): void
    {
        $images = new ImageFactory(new Configuration(Limits::default()->withMaxPixels(80_000_000)));
        $base = $images->create(new Dimensions(10_000, 6_000), Color::white())->toImage();

        try {
            ImageBuilder::from($base);
            self::fail('Expected LimitExceededException.');
        } catch (LimitExceededException $exception) {
            self::assertEquals(new LimitViolation(LimitType::Pixels, 60_000_000, 50_000_000), $exception->violation);
            self::assertSame('Limit exceeded: total pixel count is 60000000, the configured maximum is 50000000.', $exception->getMessage());
        }
    }

    #[RequiresPhpExtension('imagick')]
    public function testFramesFromAnotherImagickFactoryAreEncodedToAnimatedWebpWithoutAHandOff(): void
    {
        if (!(new ImagickDriver())->animatedWebpEncoding()->isSupported()) {
            self::markTestSkipped('This ImageMagick build cannot write animated WebP correctly.');
        }
        $spy = new RecordingDriver(new ImagickDriver());
        $source = new ImageFactory(Configuration::default(), new DriverRegistry([$spy]));
        $frames = [
            new Frame($source->create(new Dimensions(8, 8), Color::fromHex('#ff0000'))->toImage(), 100),
            new Frame($source->create(new Dimensions(8, 8), Color::fromHex('#0000ff'))->toImage(), 200),
        ];
        $spy->calls = [];

        $webp = (new ImageFactory(Configuration::default()->withForcedDriver(DriverName::Imagick)))
            ->animation($frames)
            ->encode(new WebpOutput(lossless: true));

        self::assertSame([], $spy->calls, 'no PNG hand-off between the two Imagick instances');
        self::assertSame(2, (new HeaderProbe())->probe(new BinaryString($webp->bytes))->frameCount);
    }
}
