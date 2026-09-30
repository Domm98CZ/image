<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Format\Header;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Header\ImageHeader;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Security\LimitType;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HeaderProbeTest extends TestCase
{
    /** @return iterable<string, array{string, ImageHeader}> */
    public static function validHeaders(): iterable
    {
        yield 'jpeg baseline' => [ImageBytes::jpeg(640, 480), new ImageHeader(FormatName::Jpeg, new Dimensions(640, 480))];
        yield 'jpeg progressive' => [ImageBytes::jpeg(65_535, 3, 0xC2), new ImageHeader(FormatName::Jpeg, new Dimensions(65_535, 3))];
        yield 'png rgba' => [ImageBytes::png(300, 200, 6), new ImageHeader(FormatName::Png, new Dimensions(300, 200), hasAlpha: true)];
        yield 'png rgb' => [ImageBytes::png(300, 200, 2), new ImageHeader(FormatName::Png, new Dimensions(300, 200))];
        yield 'png palette with tRNS' => [ImageBytes::png(8, 8, 3, true), new ImageHeader(FormatName::Png, new Dimensions(8, 8), hasAlpha: true)];
        yield 'png gray alpha' => [ImageBytes::png(8, 8, 4), new ImageHeader(FormatName::Png, new Dimensions(8, 8), hasAlpha: true)];
        yield 'gif single frame' => [ImageBytes::gif(20, 10, [[20, 10]]), new ImageHeader(FormatName::Gif, new Dimensions(20, 10))];
        yield 'gif animated transparent' => [
            ImageBytes::gif(20, 10, [[20, 10], [5, 5], [30, 2]], transparent: true),
            new ImageHeader(FormatName::Gif, new Dimensions(20, 10), 3, true, new Dimensions(30, 10)),
        ];
        yield 'gif without trailer' => [ImageBytes::gif(4, 4, [[4, 4], [4, 4]], trailer: false), new ImageHeader(FormatName::Gif, new Dimensions(4, 4), 2)];
        yield 'gif zero logical screen' => [ImageBytes::gif(0, 0, [[12, 7]]), new ImageHeader(FormatName::Gif, new Dimensions(12, 7))];
        yield 'webp lossy' => [ImageBytes::webpLossy(1920, 1080), new ImageHeader(FormatName::Webp, new Dimensions(1920, 1080))];
        yield 'webp lossless alpha' => [ImageBytes::webpLossless(16_384, 1, true), new ImageHeader(FormatName::Webp, new Dimensions(16_384, 1), hasAlpha: true)];
        yield 'webp lossless opaque' => [ImageBytes::webpLossless(3, 5, false), new ImageHeader(FormatName::Webp, new Dimensions(3, 5))];
        yield 'webp extended still' => [ImageBytes::webpExtended(100, 50, true), new ImageHeader(FormatName::Webp, new Dimensions(100, 50), hasAlpha: true)];
        yield 'webp extended animated' => [
            ImageBytes::webpExtended(100, 50, false, [[100, 50], [10, 10]]),
            new ImageHeader(FormatName::Webp, new Dimensions(100, 50), 2),
        ];
        yield 'avif' => [ImageBytes::avif([[1024, 768]]), new ImageHeader(FormatName::Avif, new Dimensions(1024, 768))];
        yield 'avif grid with thumbnail and alpha' => [
            ImageBytes::avif([[512, 512], [4096, 3072], [160, 120]], alpha: true),
            new ImageHeader(FormatName::Avif, new Dimensions(4096, 3072), hasAlpha: true),
        ];
        yield 'heic' => [
            ImageBytes::avif([[1024, 768]], majorBrand: 'heic', compatibleBrands: 'mif1heic'),
            new ImageHeader(FormatName::Heic, new Dimensions(1024, 768)),
        ];
        yield 'heic with alpha' => [
            ImageBytes::avif([[64, 64]], alpha: true, majorBrand: 'heic', compatibleBrands: 'mif1heic'),
            new ImageHeader(FormatName::Heic, new Dimensions(64, 64), hasAlpha: true),
        ];
    }

    #[DataProvider('validHeaders')]
    public function testProbesDeclaredHeader(string $bytes, ImageHeader $expected): void
    {
        self::assertEquals($expected, (new HeaderProbe())->probe(new BinaryString($bytes)));
    }

    /** @return iterable<string, array{string}> */
    public static function corruptedInputs(): iterable
    {
        yield 'jpeg zero height' => [ImageBytes::jpeg(10, 0)];
        yield 'jpeg without frame header' => ["\xFF\xD8\xFF\xDA\x00\x08" . str_repeat("\0", 6)];
        yield 'jpeg garbage instead of marker' => ["\xFF\xD8\xFF\xE0\x00\x04\x00\x00\x12\x34"];
        yield 'jpeg segment length below two' => ["\xFF\xD8\xFF\xE0\x00\x01"];
        yield 'png zero width' => [ImageBytes::png(0, 10)];
        yield 'png unknown color type' => [ImageBytes::png(1, 1, 5)];
        yield 'png first chunk not ihdr' => ["\x89PNG\r\n\x1A\n" . pack('N', 13) . 'IDAT' . str_repeat("\0", 17)];
        yield 'gif without frames' => ['GIF89a' . pack('vv', 1, 1) . "\x00\x00\x00\x3B"];
        yield 'gif unknown block' => ['GIF89a' . pack('vv', 1, 1) . "\x00\x00\x00\x99"];
        yield 'gif zero sized frame' => [ImageBytes::gif(10, 10, [[0, 5]])];
        yield 'webp unknown chunk' => ['RIFF' . pack('V', 12) . 'WEBPXXXX' . pack('V', 0)];
        yield 'webp lossy without start code' => ['RIFF' . pack('V', 24) . 'WEBPVP8 ' . pack('V', 10) . str_repeat("\0", 10)];
        yield 'webp lossless wrong signature' => ['RIFF' . pack('V', 24) . 'WEBPVP8L' . pack('V', 10) . str_repeat("\0", 10)];
        yield 'webp animated without frames' => [str_replace('ANMF', 'XXXX', ImageBytes::webpExtended(10, 10, false, [[1, 1]]))];
        yield 'avif without ispe' => [ImageBytes::avif([])];
        yield 'avif box larger than parent' => [ImageBytes::box('ftyp', 'avif' . "\0\0\0\0") . pack('N', 999) . 'meta'];
        yield 'avif box smaller than header' => [ImageBytes::box('ftyp', 'avif' . "\0\0\0\0") . pack('N', 4) . 'meta'];
        yield 'avif short ispe' => [ImageBytes::box('ftyp', 'avif' . "\0\0\0\0") . ImageBytes::box('meta', "\0\0\0\0" . ImageBytes::box('iprp', ImageBytes::box('ipco', ImageBytes::box('ispe', "\0\0\0\0\0\0\0\1"))))];
    }

    #[DataProvider('corruptedInputs')]
    public function testRejectsCorruptedHeaders(string $bytes): void
    {
        $this->expectException(CorruptedImageException::class);

        (new HeaderProbe())->probe(new BinaryString($bytes));
    }

    public function testRejectsDeclaredSideBeyondRepresentableMaximumAsLimitExceeded(): void
    {
        try {
            (new HeaderProbe())->probe(new BinaryString(ImageBytes::png(0x7FFF_FFFF, 1)));
            self::fail('Expected LimitExceededException.');
        } catch (LimitExceededException $exception) {
            self::assertSame(LimitType::Width, $exception->violation->limit);
            self::assertSame(Dimensions::MAX_SIDE, $exception->violation->allowed);
        }
    }

    public function testAvifRejectsExcessiveBoxCount(): void
    {
        $this->expectException(CorruptedImageException::class);

        $boxes = str_repeat(ImageBytes::box('free', ''), 10_001);
        (new HeaderProbe())->probe(new BinaryString(ImageBytes::box('ftyp', 'avif' . "\0\0\0\0") . $boxes));
    }
}
