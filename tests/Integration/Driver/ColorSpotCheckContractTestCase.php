<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\ImageBuilder;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Tests\Support\GdFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Minimal cross-driver color/pixel sanity subset, run on every driver; ColorContractTestCase adds
// the full, exhaustive suite on top and is only run against the reference (Imagick) driver - see DEC-010.
abstract class ColorSpotCheckContractTestCase extends TestCase
{
    private const SOURCE = [200, 100, 50];
    private const SOURCE_ALPHA = 128;

    protected ImageFactory $factory;

    abstract protected function driver(): DriverInterface;

    protected function setUp(): void
    {
        $driver = $this->driver();
        $this->factory = new ImageFactory(Configuration::default()->withForcedDriver($driver->name()), new DriverRegistry([$driver]));
    }

    /** @return iterable<string, array{callable(ImageBuilder): ImageBuilder, array{int, int, int}}> */
    public static function adjustments(): iterable
    {
        yield 'grayscale (Rec. 601 luma)' => [static fn(ImageBuilder $b) => $b->grayscale(), [124, 124, 124]];
        yield 'invert' => [static fn(ImageBuilder $b) => $b->invert(), [55, 155, 205]];
        yield 'brightness +20' => [static fn(ImageBuilder $b) => $b->brightness(20), [251, 151, 101]];
        yield 'brightness -100' => [static fn(ImageBuilder $b) => $b->brightness(-100), [0, 0, 0]];
        yield 'contrast +20' => [static fn(ImageBuilder $b) => $b->contrast(20), [232, 88, 16]];
        yield 'contrast -100' => [static fn(ImageBuilder $b) => $b->contrast(-100), [128, 128, 128]];
        yield 'gamma 2' => [static fn(ImageBuilder $b) => $b->gamma(2.0), [226, 160, 113]];
        yield 'gamma 0.5' => [static fn(ImageBuilder $b) => $b->gamma(0.5), [157, 39, 10]];
        yield 'colorize blue 50%' => [static fn(ImageBuilder $b) => $b->colorize(Color::fromHex('#0000ff'), 0.5), [100, 50, 153]];
        yield 'colorize full strength' => [static fn(ImageBuilder $b) => $b->colorize(Color::fromHex('#336699'), 1.0), [51, 102, 153]];
    }

    /**
     * @param callable(ImageBuilder): ImageBuilder $adjust
     * @param array{int, int, int} $expected
     */
    #[DataProvider('adjustments')]
    public function testAdjustmentMatchesFormulaAndKeepsAlpha(callable $adjust, array $expected): void
    {
        $source = GdFixtures::solidPng(8, 8, self::SOURCE[0], self::SOURCE[1], self::SOURCE[2], 63);

        $color = $adjust($this->factory->openBytes($source))->toImage()->colorAt(new Point(4, 4));

        $message = sprintf('%s: expected rgb(%d, %d, %d), got %s', $this->driver()->name()->value, ...[...$expected, $color->toHex()]);
        self::assertEqualsWithDelta($expected[0], $color->red, 2, $message);
        self::assertEqualsWithDelta($expected[1], $color->green, 2, $message);
        self::assertEqualsWithDelta($expected[2], $color->blue, 2, $message);
        self::assertEqualsWithDelta(self::SOURCE_ALPHA, $color->alpha, 2, $message . ' (alpha)');
    }

    public function testApplyPixelsWritesTheFilteredRegionBack(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(20, 10))
            ->applyPixels(static fn(PixelBuffer $pixels): PixelBuffer => PixelBuffer::filled($pixels->dimensions, Color::fromHex('#123456')), new Rectangle(new Point(0, 0), new Dimensions(10, 5)))
            ->toImage();

        self::assertSame('#123456', $image->colorAt(new Point(3, 3))->toHex());
        self::assertColorIs(GdFixtures::TOP_RIGHT, $image, new Point(15, 2));
        self::assertColorIs(GdFixtures::BOTTOM_LEFT, $image, new Point(3, 8));
    }

    /** @param array{int, int, int} $expected */
    private static function assertColorIs(array $expected, Image $image, Point $point): void
    {
        $color = $image->colorAt($point);
        self::assertSame($expected, [$color->red, $color->green, $color->blue]);
    }
}
