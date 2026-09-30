<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Operation;

use Domm98CZ\Image\Color\CallbackPixelFilter;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Operation\ApplyPixels;
use Domm98CZ\Image\Operation\Blur;
use Domm98CZ\Image\Operation\Brightness;
use Domm98CZ\Image\Operation\Colorize;
use Domm98CZ\Image\Operation\Contrast;
use Domm98CZ\Image\Operation\Gamma;
use Domm98CZ\Image\Operation\Sharpen;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorOperationTest extends TestCase
{
    /** @return iterable<string, array{callable(): object}> */
    public static function outOfRange(): iterable
    {
        yield 'brightness above 100' => [static fn() => new Brightness(101)];
        yield 'contrast above 100' => [static fn() => new Contrast(101)];
        yield 'gamma zero' => [static fn() => new Gamma(0.0)];
        yield 'blur zero' => [static fn() => new Blur(0.0)];
        yield 'sharpen above 5' => [static fn() => new Sharpen(6.0)];
        yield 'colorize strength above 1' => [static fn() => new Colorize(Color::black(), 1.5)];
    }

    /** @param callable(): object $create */
    #[DataProvider('outOfRange')]
    public function testRejectsParametersOutOfRange(callable $create): void
    {
        $this->expectException(InvalidOperationException::class);

        $create();
    }

    public function testDerivedParameters(): void
    {
        self::assertSame(51, (new Brightness(20))->offset());
        self::assertSame(-255, (new Brightness(-100))->offset());
        self::assertEqualsWithDelta(1.44, (new Contrast(20))->factor(), 1e-9);
        self::assertSame(0.0, (new Contrast(-100))->factor());
        self::assertSame(4.0, (new Contrast(100))->factor());
    }

    public function testApplyPixelsDefaultsToTheWholeImageAndRejectsRegionsOutsideIt(): void
    {
        $operation = new ApplyPixels(new CallbackPixelFilter(static fn(PixelBuffer $pixels): PixelBuffer => $pixels));

        self::assertTrue($operation->area(new Dimensions(8, 6))->equals(Rectangle::covering(new Dimensions(8, 6))));

        $this->expectException(InvalidOperationException::class);
        (new ApplyPixels($operation->filter, new Rectangle(new Point(4, 4), new Dimensions(5, 5))))->area(new Dimensions(8, 6));
    }

    public function testApplyPixelsRejectsFiltersThatChangeDimensions(): void
    {
        $shrink = new ApplyPixels(new CallbackPixelFilter(static fn(PixelBuffer $pixels): PixelBuffer => PixelBuffer::filled(new Dimensions(1, 1), Color::black())));

        $this->expectException(InvalidOperationException::class);

        $shrink->apply(PixelBuffer::filled(new Dimensions(2, 2), Color::white()));
    }
}
