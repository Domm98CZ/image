<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Analysis;

use Domm98CZ\Image\Analysis\Histogram;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Geometry\Dimensions;
use PHPUnit\Framework\TestCase;

final class HistogramTest extends TestCase
{
    public function testCountsEachChannelIndependently(): void
    {
        $pixels = new PixelBuffer(new Dimensions(2, 1), pack('C8', 10, 20, 30, 255, 10, 200, 30, 128));
        $histogram = Histogram::of($pixels);

        self::assertSame(2, $histogram->red[10]);
        self::assertSame(1, $histogram->green[20]);
        self::assertSame(1, $histogram->green[200]);
        self::assertSame(2, $histogram->blue[30]);
        self::assertSame(1, $histogram->alpha[255]);
        self::assertSame(1, $histogram->alpha[128]);
        self::assertSame(2, array_sum($histogram->red));
    }

    public function testFilledBufferConcentratesEverythingInOneBucket(): void
    {
        $histogram = Histogram::of(PixelBuffer::filled(new Dimensions(3, 3), Color::fromHex('#ff0000')));

        self::assertSame(9, $histogram->red[255]);
        self::assertSame(9, $histogram->green[0]);
        self::assertSame(9, $histogram->blue[0]);
    }
}
