<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Metadata;

use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Metadata\ExifOrientationReader;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExifOrientationReaderTest extends TestCase
{
    /** @return iterable<string, array{string, FormatName, Orientation}> */
    public static function images(): iterable
    {
        yield 'jpeg little endian' => [ImageBytes::jpegWithExif(10, 10, ImageBytes::exifTiff(6)), FormatName::Jpeg, Orientation::RightTop];
        yield 'jpeg big endian' => [ImageBytes::jpegWithExif(10, 10, ImageBytes::exifTiff(8, false)), FormatName::Jpeg, Orientation::LeftBottom];
        yield 'jpeg without exif' => [ImageBytes::jpeg(10, 10), FormatName::Jpeg, Orientation::TopLeft];
        yield 'png eXIf chunk' => [ImageBytes::pngWithExif(10, 10, ImageBytes::exifTiff(3)), FormatName::Png, Orientation::BottomRight];
        yield 'png without exif' => [ImageBytes::png(10, 10), FormatName::Png, Orientation::TopLeft];
        yield 'webp raw tiff' => [ImageBytes::webpWithExif(10, 10, ImageBytes::exifTiff(5)), FormatName::Webp, Orientation::LeftTop];
        yield 'webp with exif prefix' => [ImageBytes::webpWithExif(10, 10, "Exif\0\0" . ImageBytes::exifTiff(7)), FormatName::Webp, Orientation::RightBottom];
        yield 'gif never carries exif' => [ImageBytes::gif(1, 1, [[1, 1]]), FormatName::Gif, Orientation::TopLeft];
        yield 'heic orientation not read yet' => [ImageBytes::heic(), FormatName::Heic, Orientation::TopLeft];
        yield 'invalid orientation value' => [ImageBytes::jpegWithExif(10, 10, ImageBytes::exifTiff(9)), FormatName::Jpeg, Orientation::TopLeft];
        yield 'unknown byte order' => [ImageBytes::jpegWithExif(10, 10, 'XX' . substr(ImageBytes::exifTiff(6), 2)), FormatName::Jpeg, Orientation::TopLeft];
        yield 'truncated tiff' => [ImageBytes::jpegWithExif(10, 10, substr(ImageBytes::exifTiff(6), 0, 12)), FormatName::Jpeg, Orientation::TopLeft];
    }

    #[DataProvider('images')]
    public function testReadsOrientation(string $bytes, FormatName $format, Orientation $expected): void
    {
        self::assertSame($expected, (new ExifOrientationReader())->read(new BinaryString($bytes), $format));
    }

    public function testRandomlyCorruptedExifNeverThrows(): void
    {
        $bytes = ImageBytes::jpegWithExif(10, 10, ImageBytes::exifTiff(6));
        mt_srand(42);
        for ($iteration = 0; $iteration < 3_000; ++$iteration) {
            $mutated = $bytes;
            $mutated[mt_rand(2, 60)] = chr(mt_rand(0, 255));
            (new ExifOrientationReader())->read(new BinaryString($mutated), FormatName::Jpeg);
        }

        $this->addToAssertionCount(1);
    }
}
