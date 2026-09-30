<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Imagick;

use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Driver\Gd\GdDriver;
use Domm98CZ\Image\Driver\Imagick\ImagickDriver;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Tests\Support\ImagickFixtures;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('imagick')]
final class WebpAnimationTest extends TestCase
{
    protected function setUp(): void
    {
        $imagick = new ImagickDriver();
        if (!$imagick->animatedWebpDecoding()->isSupported() || !$imagick->animatedWebpEncoding()->isSupported()) {
            self::markTestSkipped('This ImageMagick build cannot read and write animated WebP correctly.');
        }
    }

    public function testDecodesImageMagickAnimatedWebp(): void
    {
        $webp = ImagickFixtures::coloredAnimation('webp', 60, 40, 3);

        $animation = (new ImageFactory())->openAnimationBytes($webp)->toAnimation();

        self::assertSame(3, $animation->frameCount());
        self::assertSame(0, $animation->playCount);
        self::assertSame([100, 200, 300], array_map(static fn(Frame $frame): int => $frame->delayMilliseconds, $animation->frames));
        // The fixture is lossy WebP, so allow a couple of levels of drift.
        $green = $animation->frames[0]->image->colorAt(new Point(30, 20));
        self::assertEqualsWithDelta([0, 255, 0], [$green->red, $green->green, $green->blue], 3);
    }

    #[RequiresPhpExtension('gd')]
    public function testGifFramesDecodedOnGdAreEncodedToAnimatedWebpThroughImagick(): void
    {
        $factory = new ImageFactory();

        $webp = $factory->openAnimationBytes(ImagickFixtures::disposalShowcaseGif())->scale(2.0)->encode(new WebpOutput(lossless: true));
        $header = (new HeaderProbe())->probe(new BinaryString($webp->bytes));
        $decoded = $factory->openAnimationBytes($webp->bytes)->toAnimation();

        self::assertSame(5, $header->frameCount);
        self::assertSame(2, $decoded->playCount);
        self::assertSame([100, 200, 300, 400, 500], array_map(static fn(Frame $frame): int => $frame->delayMilliseconds, $decoded->frames));
        self::assertSame('#0000ff', $decoded->frames[1]->image->colorAt(new Point(40, 40))->toHex());
        self::assertTrue($decoded->frames[2]->image->colorAt(new Point(40, 40))->isFullyTransparent());
        self::assertSame('#00ff00', $decoded->frames[2]->image->colorAt(new Point(90, 50))->toHex());
    }

    #[RequiresPhpExtension('gd')]
    public function testGdOnlySetupsReportThatAnimatedWebpNeedsImagick(): void
    {
        $this->assertGdOnlyRejectsAnimatedWebp();
    }

    private function assertGdOnlyRejectsAnimatedWebp(): void
    {
        $gdOnly = new ImageFactory(Configuration::default()->withForcedDriver(DriverName::Gd), new DriverRegistry([new GdDriver()]));

        $this->expectException(UnsupportedFormatException::class);
        $this->expectExceptionMessage('needs ext-imagick');

        $gdOnly->openAnimationBytes(ImagickFixtures::coloredAnimation('webp', 20, 20, 2));
    }
}
