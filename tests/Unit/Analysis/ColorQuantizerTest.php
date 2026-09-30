<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Analysis;

use Domm98CZ\Image\Analysis\ColorQuantizer;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use PHPUnit\Framework\TestCase;

final class ColorQuantizerTest extends TestCase
{
    public function testASolidBufferProducesExactlyOneCluster(): void
    {
        $palette = ColorQuantizer::palette(PixelBuffer::filled(new Dimensions(4, 4), Color::fromHex('#336699')), 5);

        self::assertCount(1, $palette);
        self::assertTrue($palette[0]->equals(Color::fromHex('#336699')));
    }

    public function testTwoEvenClustersSplitCleanlyOnTheWidestChannel(): void
    {
        // Median-cut splits at the population median of the widest channel (red: 0,0,255,255), not by
        // value uniformity - two equal-sized, non-overlapping groups is the case it separates exactly.
        $bytes = str_repeat(pack('C4', 0, 0, 255, 255), 2) . str_repeat(pack('C4', 255, 0, 0, 255), 2);
        $palette = ColorQuantizer::palette(new PixelBuffer(new Dimensions(4, 1), $bytes), 2);

        self::assertCount(2, $palette);
        self::assertTrue($palette[0]->equals(Color::fromHex('#0000ff')));
        self::assertTrue($palette[1]->equals(Color::fromHex('#ff0000')));
    }

    public function testRejectsANonPositiveCount(): void
    {
        $this->expectException(InvalidOperationException::class);

        ColorQuantizer::palette(PixelBuffer::filled(new Dimensions(1, 1), Color::black()), 0);
    }
}
