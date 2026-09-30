<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Format\Output\GifOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Tests\Support\GdFixtures;
use Domm98CZ\Image\Tests\Support\ImagickFixtures;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// Minimal cross-driver animation sanity subset, run on every driver; AnimationContractTestCase adds
// the full, exhaustive suite on top and is only run against the reference (Imagick) driver - see DEC-010.
abstract class AnimationSpotCheckContractTestCase extends TestCase
{
    protected ImageFactory $factory;

    abstract protected function driver(): DriverInterface;

    protected function setUp(): void
    {
        $driver = $this->driver();
        $this->factory = new ImageFactory(Configuration::default()->withForcedDriver($driver->name()), new DriverRegistry([$driver]));
    }

    #[RequiresPhpExtension('imagick')]
    public function testOperationsApplyToEveryFrame(): void
    {
        $gif = ImagickFixtures::coloredAnimation('gif', 60, 40, 3);

        $animation = $this->factory->openAnimationBytes($gif)->scale(0.5)->toAnimation();

        self::assertEquals(new Dimensions(30, 20), $animation->dimensions);
        self::assertSame(3, $animation->frameCount());
        self::assertSame('#00ff00', $animation->frames[0]->image->colorAt(new Point(15, 10))->toHex());
        self::assertSame('#3cc300', $animation->frames[1]->image->colorAt(new Point(15, 10))->toHex());
    }

    public function testGifOutputKeepsTransparency(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(20, 10, transparentBottomRight: true))->toImage();

        $encoded = $this->factory->animation([new Frame($image)], 1)->encode(new GifOutput());
        $reopened = $this->factory->openBytes($encoded->bytes)->toImage();

        self::assertTrue($reopened->colorAt(new Point(15, 8))->isFullyTransparent());
        self::assertSame('#ff0000', $reopened->colorAt(new Point(2, 2))->toHex());
    }
}
