<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Metadata;

use Domm98CZ\Image\Color\Profile\ColorProfile;
use Domm98CZ\Image\Color\Profile\RgbMatrixProfile;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Metadata\ColorProfileReader;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\TestCase;

final class ColorProfileReaderTest extends TestCase
{
    public function testReadsAProfileSplitOverOutOfOrderJpegSegmentsAndSkipsFillBytes(): void
    {
        $profile = RgbMatrixProfile::linearSrgbPrimaries()->bytes();
        $jpeg = ImageBytes::jpegWithIcc(4, 4, [[2, substr($profile, 300)], [1, substr($profile, 0, 300)]], 2);
        $jpeg = "\xFF\xD8\xFF\xFF" . substr($jpeg, 2);

        self::assertSame('Linear Rec. 709', self::read($jpeg, FormatName::Jpeg)?->description);
    }

    public function testIgnoresAJpegProfileWithAMissingSegment(): void
    {
        $profile = RgbMatrixProfile::linearSrgbPrimaries()->bytes();

        self::assertNull(self::read(ImageBytes::jpegWithIcc(4, 4, [[1, substr($profile, 0, 300)]], 2), FormatName::Jpeg));
        self::assertNull(self::read(ImageBytes::jpegWithIcc(4, 4, [[1, substr($profile, 0, 300)], [3, substr($profile, 300)]], 2), FormatName::Jpeg));
    }

    public function testReadsTheCompressedPngProfile(): void
    {
        $png = ImageBytes::pngWithIcc(4, 4, (string) gzcompress(RgbMatrixProfile::srgb()->bytes()));

        $profile = self::read($png, FormatName::Png);

        self::assertTrue($profile?->isSrgb());
    }

    public function testRefusesToInflateAPngProfileBomb(): void
    {
        $bomb = (string) gzcompress(str_repeat("\0", 32 * 1024 * 1024), 9);
        self::assertLessThan(64_000, strlen($bomb));

        memory_reset_peak_usage();
        $started = memory_get_usage();
        self::assertNull(self::read(ImageBytes::pngWithIcc(4, 4, $bomb), FormatName::Png));
        self::assertLessThan(8 * 1024 * 1024, memory_get_peak_usage() - $started, 'inflating stops within a step of the 4 MB cap');
    }

    public function testIgnoresACorruptPngProfileWithoutLeakingWarnings(): void
    {
        self::assertNull(self::read(ImageBytes::pngWithIcc(4, 4, 'not zlib data at all'), FormatName::Png));
    }

    public function testReadsTheWebpIccpChunk(): void
    {
        $webp = ImageBytes::webpWithIcc(4, 4, RgbMatrixProfile::linearSrgbPrimaries()->bytes());

        self::assertTrue(self::read($webp, FormatName::Webp)?->needsConversion());
    }

    public function testReadsAvifColrProfilesButNotCodedPrimaries(): void
    {
        $profile = RgbMatrixProfile::linearSrgbPrimaries()->bytes();

        self::assertSame('Linear Rec. 709', self::read(ImageBytes::avifWithColr(4, 4, 'prof', $profile), FormatName::Avif)?->description);
        self::assertSame('Linear Rec. 709', self::read(ImageBytes::avifWithColr(4, 4, 'rICC', $profile), FormatName::Avif)?->description);
        self::assertNull(self::read(ImageBytes::avifWithColr(4, 4, 'nclx', pack('nnn', 12, 13, 6) . "\x80"), FormatName::Avif));
    }

    public function testImagesWithoutProfilesAndGifsReadAsNone(): void
    {
        self::assertNull(self::read(ImageBytes::jpeg(4, 4), FormatName::Jpeg));
        self::assertNull(self::read(ImageBytes::png(4, 4), FormatName::Png));
        self::assertNull(self::read(ImageBytes::webpLossless(4, 4, false), FormatName::Webp));
        self::assertNull(self::read(ImageBytes::avif([[4, 4]]), FormatName::Avif));
        self::assertNull(self::read(ImageBytes::gif(4, 4, [[4, 4]]), FormatName::Gif));
        self::assertNull(self::read(ImageBytes::heic(), FormatName::Heic));
    }

    public function testTruncatedContainersReadAsNone(): void
    {
        $jpeg = ImageBytes::jpegWithIcc(4, 4, [[1, RgbMatrixProfile::srgb()->bytes()]], 1);

        self::assertNull(self::read(substr($jpeg, 0, 40), FormatName::Jpeg));
        self::assertNull(self::read(substr(ImageBytes::webpWithIcc(4, 4, RgbMatrixProfile::srgb()->bytes()), 0, 60), FormatName::Webp));
    }

    private static function read(string $bytes, FormatName $format): ?ColorProfile
    {
        return (new ColorProfileReader())->read(new BinaryString($bytes), $format);
    }
}
