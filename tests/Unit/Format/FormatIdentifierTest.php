<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Format;

use Domm98CZ\Image\Exception\UnrecognizedFormatException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatIdentifier;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatIdentifierTest extends TestCase
{
    /** @return iterable<string, array{string, FormatName}> */
    public static function signatures(): iterable
    {
        yield 'jpeg' => [ImageBytes::jpeg(1, 1), FormatName::Jpeg];
        yield 'png' => [ImageBytes::png(1, 1), FormatName::Png];
        yield 'gif89a' => [ImageBytes::gif(1, 1, [[1, 1]]), FormatName::Gif];
        yield 'gif87a' => ['GIF87a' . str_repeat("\0", 10), FormatName::Gif];
        yield 'webp' => [ImageBytes::webpLossy(1, 1), FormatName::Webp];
        yield 'avif major brand' => [ImageBytes::avif([[1, 1]]), FormatName::Avif];
        yield 'avif sequence brand' => [ImageBytes::avif([[1, 1]], majorBrand: 'avis'), FormatName::Avif];
        yield 'avif compatible brand only' => [ImageBytes::avif([[1, 1]], majorBrand: 'mif1', compatibleBrands: 'miafavif'), FormatName::Avif];
        yield 'heic major brand' => [ImageBytes::avif([[1, 1]], majorBrand: 'heic', compatibleBrands: 'mif1heic'), FormatName::Heic];
        yield 'heic hevc brand' => [ImageBytes::avif([[1, 1]], majorBrand: 'hevc'), FormatName::Heic];
        yield 'heic compatible brand only' => [ImageBytes::avif([[1, 1]], majorBrand: 'isom', compatibleBrands: 'isomheic'), FormatName::Heic];
    }

    #[DataProvider('signatures')]
    public function testIdentifiesBySignatureOnly(string $bytes, FormatName $expected): void
    {
        self::assertSame($expected, (new FormatIdentifier())->identify(new BinaryString($bytes)));
    }

    /** @return iterable<string, array{string}> */
    public static function unrecognized(): iterable
    {
        yield 'text' => ['hello world, not an image'];
        yield 'riff but not webp' => ['RIFF' . pack('V', 4) . 'WAVE'];
        yield 'truncated png signature' => ["\x89PNG"];
        yield 'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"/>'];
    }

    #[DataProvider('unrecognized')]
    public function testRejectsUnrecognizedSignatures(string $bytes): void
    {
        $this->expectException(UnrecognizedFormatException::class);

        (new FormatIdentifier())->identify(new BinaryString($bytes));
    }

    public function testRejectsEmptyInput(): void
    {
        $this->expectExceptionObject(UnrecognizedFormatException::emptyInput());

        (new FormatIdentifier())->identify(new BinaryString(''));
    }
}
