<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Format\Png;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Header\ImageHeader;
use Domm98CZ\Image\Format\Png\RgbaPngWriter;
use Domm98CZ\Image\Geometry\Dimensions;
use PHPUnit\Framework\TestCase;

final class RgbaPngWriterTest extends TestCase
{
    public function testWritesUnfilteredRgbaRowsThatRoundTrip(): void
    {
        $pixels = new PixelBuffer(new Dimensions(2, 2), "\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10");

        $png = (new RgbaPngWriter())->write($pixels);

        self::assertEquals(new ImageHeader(FormatName::Png, new Dimensions(2, 2), hasAlpha: true), (new HeaderProbe())->probe(new BinaryString($png)));
        $idatLength = unpack('N', $png, 33)[1] ?? 0;
        self::assertSame("\0\x01\x02\x03\x04\x05\x06\x07\x08\0\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10", gzuncompress(substr($png, 41, $idatLength)));
    }

    public function testChunksCarryValidCrc(): void
    {
        $png = (new RgbaPngWriter())->write(PixelBuffer::filled(new Dimensions(3, 3), Color::white()));

        for ($offset = 8; $offset < strlen($png); $offset += 12 + $length) {
            $length = unpack('N', $png, $offset)[1] ?? 0;
            $crc = unpack('N', $png, $offset + 8 + $length)[1] ?? 0;
            self::assertSame(crc32(substr($png, $offset + 4, 4 + $length)), $crc);
        }
    }
}
