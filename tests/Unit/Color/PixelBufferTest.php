<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Color;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Exception\InvalidPixelBufferException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use PHPUnit\Framework\TestCase;

final class PixelBufferTest extends TestCase
{
    public function testRejectsWrongLength(): void
    {
        $this->expectException(InvalidPixelBufferException::class);

        new PixelBuffer(new Dimensions(2, 2), str_repeat("\0", 15));
    }

    public function testAddressesPixelsRowMajor(): void
    {
        $buffer = new PixelBuffer(new Dimensions(2, 2), "\x01\x02\x03\x04" . "\x05\x06\x07\x08" . "\x09\x0A\x0B\x0C" . "\x0D\x0E\x0F\x10");

        self::assertTrue($buffer->colorAt(new Point(1, 0))->equals(new Color(5, 6, 7, 8)));
        self::assertTrue($buffer->colorAt(new Point(0, 1))->equals(new Color(9, 10, 11, 12)));
        self::assertSame(8, $buffer->stride());
    }

    public function testFilled(): void
    {
        $buffer = PixelBuffer::filled(new Dimensions(3, 1), Color::fromHex('#11223344'));

        self::assertSame(str_repeat("\x11\x22\x33\x44", 3), $buffer->bytes);
    }

    public function testRejectsPointOutside(): void
    {
        $this->expectException(InvalidOperationException::class);

        PixelBuffer::filled(new Dimensions(3, 1), Color::black())->colorAt(new Point(3, 0));
    }
}
