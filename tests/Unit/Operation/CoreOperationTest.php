<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Operation;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Operation\ExpansionContext;
use Domm98CZ\Image\Operation\FitInside;
use Domm98CZ\Image\Operation\FitOutside;
use Domm98CZ\Image\Operation\HueSaturation;
use Domm98CZ\Image\Operation\Interpolation;
use Domm98CZ\Image\Operation\Mask;
use Domm98CZ\Image\Operation\Opacity;
use Domm98CZ\Image\Operation\Pad;
use Domm98CZ\Image\Operation\Pixelate;
use Domm98CZ\Image\Operation\Resize;
use Domm98CZ\Image\Operation\RoundedCorners;
use Domm98CZ\Image\Operation\Scale;
use Domm98CZ\Image\Operation\Sepia;
use Domm98CZ\Image\Operation\Thumbnail;
use Domm98CZ\Image\Operation\Trim;
use Domm98CZ\Image\Tests\Fake\FakeDriver;
use Domm98CZ\Image\Tests\Fake\FakeImageFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CoreOperationTest extends TestCase
{
    /** @return iterable<string, array{callable(): object}> */
    public static function outOfRange(): iterable
    {
        yield 'negative left padding' => [static fn() => new Pad(-1, 0, 0, 0)];
        yield 'opacity above one' => [static fn() => new Opacity(1.1)];
        yield 'opacity nan' => [static fn() => new Opacity(NAN)];
        yield 'hue outside range' => [static fn() => new HueSaturation(181, 0)];
        yield 'saturation outside range' => [static fn() => new HueSaturation(0, -101)];
        yield 'pixelate zero' => [static fn() => new Pixelate(0)];
        yield 'negative corner radius' => [static fn() => new RoundedCorners(-1)];
    }

    /** @param callable(): object $create */
    #[DataProvider('outOfRange')]
    public function testRejectsParametersOutOfRange(callable $create): void
    {
        $this->expectException(InvalidOperationException::class);

        $create();
    }

    public function testPadGrowsTheCanvasAndDefaultsToTransparency(): void
    {
        $pad = new Pad(2, 3, 4, 5);

        self::assertEquals(new Dimensions(16, 16), $pad->resultingDimensions(new Dimensions(10, 8)));
        self::assertTrue($pad->background->isFullyTransparent());
    }

    public function testTrimKeepsItsConservativeForecast(): void
    {
        $input = new Dimensions(10, 8);

        self::assertSame($input, (new Trim())->resultingDimensions($input));
    }

    public function testOpacityAndSepiaPreserveDimensionsAndApplyTheirFormulae(): void
    {
        $pixels = new PixelBuffer(new Dimensions(1, 1), pack('C4', 100, 150, 200, 128));

        self::assertSame([100, 150, 200, 64], self::rgba((new Opacity(0.5))->apply($pixels)));
        self::assertSame([192, 171, 134, 128], self::rgba((new Sepia())->apply($pixels)));
    }

    public function testHueSaturationRotatesHueAndCanRemoveSaturation(): void
    {
        $red = new PixelBuffer(new Dimensions(1, 1), pack('C4', 255, 0, 0, 123));

        self::assertSame([0, 255, 0, 123], self::rgba((new HueSaturation(120, 0))->apply($red)));
        self::assertSame([128, 128, 128, 123], self::rgba((new HueSaturation(0, -100))->apply($red)));
    }

    public function testPixelateUsesTheAverageOfEveryBlock(): void
    {
        $pixels = new PixelBuffer(new Dimensions(2, 2), pack('C16', 0, 0, 0, 0, 100, 100, 100, 100, 200, 200, 200, 200, 255, 255, 255, 255));

        self::assertSame(array_fill(0, 4, [139, 139, 139, 139]), self::pixels((new Pixelate(2))->apply($pixels)));
    }

    public function testRoundedCornersClearOnlyTheOutsidePixels(): void
    {
        $pixels = PixelBuffer::filled(new Dimensions(4, 4), Color::white());
        $rounded = (new RoundedCorners(2))->apply($pixels);

        self::assertSame(0, $rounded->colorAt(new \Domm98CZ\Image\Geometry\Point(0, 0))->alpha);
        self::assertSame(255, $rounded->colorAt(new \Domm98CZ\Image\Geometry\Point(1, 0))->alpha);
        self::assertSame(0, $rounded->colorAt(new \Domm98CZ\Image\Geometry\Point(3, 3))->alpha);
    }

    public function testMaskMultipliesAlphaAndRequiresMatchingDimensions(): void
    {
        $mask = FakeImageFactory::with(new FakeDriver(pixelColor: new Color(0, 0, 0, 128)))->create(new Dimensions(1, 1))->toImage();
        $source = PixelBuffer::filled(new Dimensions(1, 1), new Color(20, 30, 40, 200));

        self::assertSame([20, 30, 40, 100], self::rgba((new Mask($mask))->apply($source)));

        $this->expectException(InvalidOperationException::class);
        (new Mask($mask))->apply(PixelBuffer::filled(new Dimensions(2, 1), Color::white()));
    }

    public function testInterpolationFlowsThroughResizeComposites(): void
    {
        $dimensions = new Dimensions(100, 50);
        $bicubic = Interpolation::Bicubic;

        self::assertSame($bicubic, (new Resize(new Dimensions(20, 10), $bicubic))->interpolation);
        self::assertSame($bicubic, (new Scale(0.5, $bicubic))->expand(new ExpansionContext($dimensions))[0]->interpolation);
        self::assertSame($bicubic, (new FitInside(new Dimensions(40, 40), interpolation: $bicubic))->expand(new ExpansionContext($dimensions))[0]->interpolation);
        self::assertSame($bicubic, (new FitOutside(new Dimensions(40, 40), interpolation: $bicubic))->expand(new ExpansionContext($dimensions))[0]->interpolation);
        $thumbnail = (new Thumbnail(new Dimensions(40, 40), interpolation: $bicubic))->expand(new ExpansionContext($dimensions));
        self::assertSame($bicubic, $thumbnail[0]->interpolation);
    }

    /** @return list<int> */
    private static function rgba(PixelBuffer $pixels): array
    {
        return array_values((array) unpack('C4', $pixels->bytes));
    }

    /** @return list<list<int>> */
    private static function pixels(PixelBuffer $pixels): array
    {
        return array_map(static fn(string $pixel): array => array_values((array) unpack('C4', $pixel)), str_split($pixels->bytes, PixelBuffer::BYTES_PER_PIXEL));
    }
}
