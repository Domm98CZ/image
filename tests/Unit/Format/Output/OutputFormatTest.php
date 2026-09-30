<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Format\Output;

use Domm98CZ\Image\Exception\InvalidEncodingOptionException;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\OutputFormats;
use Domm98CZ\Image\Format\Output\OutputOptions;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Format\Output\WebpOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutputFormatTest extends TestCase
{
    /** @return iterable<string, array{callable(): object}> */
    public static function invalidOptions(): iterable
    {
        yield 'jpeg quality zero' => [static fn() => new JpegOutput(quality: 0)];
        yield 'jpeg quality above 100' => [static fn() => new JpegOutput(quality: 101)];
        yield 'png compression above 9' => [static fn() => new PngOutput(compressionLevel: 10)];
        yield 'png compression negative' => [static fn() => new PngOutput(compressionLevel: -1)];
        yield 'webp quality zero' => [static fn() => new WebpOutput(quality: 0)];
        yield 'avif quality above 100' => [static fn() => new AvifOutput(quality: 101)];
        yield 'avif speed above 10' => [static fn() => new AvifOutput(speed: 11)];
    }

    /** @param callable(): object $create */
    #[DataProvider('invalidOptions')]
    public function testRejectsOutOfRangeOptions(callable $create): void
    {
        $this->expectException(InvalidEncodingOptionException::class);

        $create();
    }

    public function testDefaultsPerFormat(): void
    {
        foreach (FormatName::cases() as $format) {
            if ($format === FormatName::Heic) {
                continue;
            }
            self::assertSame($format, OutputFormats::defaultFor($format)->format());
        }
        self::assertSame(85, (new JpegOutput())->quality);
        self::assertTrue((new JpegOutput())->background->isOpaque());
    }

    public function testHeicHasNoEncoder(): void
    {
        $this->expectException(UnsupportedFormatException::class);

        OutputFormats::defaultFor(FormatName::Heic);
    }

    public function testPngKeepsTheAlphaChannelOnlyOnRequest(): void
    {
        self::assertFalse((new PngOutput())->keepAlphaChannel);
        self::assertTrue((new PngOutput(compressionLevel: 1, keepAlphaChannel: true))->keepAlphaChannel);
    }

    public function testExtensionLookup(): void
    {
        self::assertSame(FormatName::Jpeg, FormatName::tryFromFileExtension('JPEG'));
        self::assertSame(FormatName::Avif, FormatName::tryFromFileExtension('avif'));
        self::assertNull(FormatName::tryFromFileExtension('bmp'));
        self::assertNull(FormatName::tryFromFileExtension(''));
    }

    public function testExpectRejectsMismatchedOutputType(): void
    {
        $this->expectException(InvalidEncodingOptionException::class);

        OutputOptions::expect(JpegOutput::class, new PngOutput());
    }
}
