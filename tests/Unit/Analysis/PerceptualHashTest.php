<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Analysis;

use Domm98CZ\Image\Analysis\PerceptualHash;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use PHPUnit\Framework\TestCase;

final class PerceptualHashTest extends TestCase
{
    public function testAFlatBufferHasAnAllZeroHash(): void
    {
        $hash = PerceptualHash::of(PixelBuffer::filled(new Dimensions(9, 8), Color::fromHex('#808080')));

        self::assertSame('0000000000000000', $hash);
    }

    public function testHashesAreDeterministic(): void
    {
        $pixels = PixelBuffer::filled(new Dimensions(16, 16), Color::fromHex('#123456'));

        self::assertSame(PerceptualHash::of($pixels), PerceptualHash::of($pixels));
    }

    public function testIdenticalBuffersHaveZeroDistance(): void
    {
        $hash = PerceptualHash::of(PixelBuffer::filled(new Dimensions(9, 8), Color::fromHex('#a1b2c3')));

        self::assertSame(0, PerceptualHash::hammingDistance($hash, $hash));
    }

    public function testDistanceCountsDifferingBits(): void
    {
        self::assertSame(0, PerceptualHash::hammingDistance('0000000000000000', '0000000000000000'));
        self::assertSame(1, PerceptualHash::hammingDistance('0000000000000000', '0000000000000001'));
        self::assertSame(4, PerceptualHash::hammingDistance('0000000000000000', 'f000000000000000'));
        self::assertSame(64, PerceptualHash::hammingDistance('0000000000000000', 'ffffffffffffffff'));
    }

    public function testRejectsHashesOfDifferentLength(): void
    {
        $this->expectException(InvalidOperationException::class);

        PerceptualHash::hammingDistance('00', '0000');
    }
}
