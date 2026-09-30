<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Animation\AnimatedImage;
use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Format\Output\GifOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Tests\Support\ImagickFixtures;
use Imagick;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

// The full, exhaustive contract - only run against the reference (Imagick) driver, see DEC-010.
// GD gets the spot-check subset in AnimationSpotCheckContractTestCase (parent of this class too).
// ImageMagick is the independent reference for the container codec, so those tests need ext-imagick on any driver.
abstract class AnimationContractTestCase extends AnimationSpotCheckContractTestCase
{
    #[RequiresPhpExtension('imagick')]
    public function testCompositedFramesMatchImageMagickCoalesce(): void
    {
        $gif = ImagickFixtures::disposalShowcaseGif();

        $animation = $this->factory->openAnimationBytes($gif)->toAnimation();

        self::assertSame(5, $animation->frameCount());
        self::assertSame(2, $animation->playCount);
        self::assertSame([100, 200, 300, 400, 500], array_map(static fn(Frame $frame): int => $frame->delayMilliseconds, $animation->frames));
        self::assertMatchesReference($gif, $animation);
    }

    #[RequiresPhpExtension('imagick')]
    public function testReencodedGifIsReadBackIdenticallyByImageMagick(): void
    {
        $animation = $this->factory->openAnimationBytes(ImagickFixtures::disposalShowcaseGif())->toAnimation();

        $encoded = $this->factory->animation($animation->frames, $animation->playCount)->encode(new GifOutput());

        $reference = new Imagick();
        $reference->readImageBlob($encoded->bytes);
        self::assertSame(5, $reference->getNumberImages());
        self::assertSame(2, $reference->getImageIterations());
        self::assertMatchesReference($encoded->bytes, $animation);
    }

    public function testBuildsAnAnimationFromScratch(): void
    {
        $frames = array_map(
            fn(string $hex): Frame => new Frame($this->factory->create(new Dimensions(16, 16), Color::fromHex($hex))->toImage(), 70),
            ['#ff0000', '#00ff00', '#0000ff'],
        );

        $encoded = $this->factory->animation($frames)->encode(new GifOutput());
        $decoded = $this->factory->openAnimationBytes($encoded->bytes)->toAnimation();

        self::assertSame(['#ff0000', '#00ff00', '#0000ff'], array_map(static fn(Frame $frame): string => $frame->image->colorAt(new Point(8, 8))->toHex(), $decoded->frames));
        self::assertSame([70, 70, 70], array_map(static fn(Frame $frame): int => $frame->delayMilliseconds, $decoded->frames));
        self::assertSame(0, $decoded->playCount);
    }

    private static function assertMatchesReference(string $gif, AnimatedImage $animation): void
    {
        $reference = new Imagick();
        $reference->readImageBlob($gif);
        $reference = $reference->coalesceImages();
        foreach ($animation->frames as $index => $frame) {
            $reference->setIteratorIndex($index);
            for ($y = 2; $y < 40; $y += 5) {
                for ($x = 2; $x < 60; $x += 5) {
                    $pixel = $reference->getImagePixelColor($x, $y);
                    $expected = (int) round($pixel->getColorValue(Imagick::COLOR_ALPHA) * 255) === 0
                        ? 'transparent'
                        : sprintf('#%02x%02x%02x', ...array_map(static fn(int $channel): int => (int) round($pixel->getColorValue($channel) * 255), [Imagick::COLOR_RED, Imagick::COLOR_GREEN, Imagick::COLOR_BLUE]));
                    $color = $frame->image->colorAt(new Point($x, $y));
                    $actual = $color->isFullyTransparent() ? 'transparent' : sprintf('#%02x%02x%02x', $color->red, $color->green, $color->blue);
                    self::assertSame($expected, $actual, sprintf('frame %d at %d,%d', $index, $x, $y));
                }
            }
        }
    }
}
