<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Optimization;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Optimization\MetadataStripper;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Payloads from the hostile-input review: everything that is not image data has to go.
final class MetadataStripperTest extends TestCase
{
    private const TRAILER = '<html><script>SECRET-TRAILER</script></html>';

    public function testJpegEndsAtTheEndOfImageMarker(): void
    {
        $stripped = self::strip(ImageBytes::jpeg(4, 4) . self::TRAILER, FormatName::Jpeg);

        self::assertSame(44, strlen(self::TRAILER));
        self::assertSame(self::jpeg(), $stripped);
    }

    public function testProgressiveJpegLosesSegmentsBetweenScansButKeepsTheScans(): void
    {
        $frame = self::segment(0xC2, "\x08" . pack('nn', 2, 2) . "\x01\x01\x11\x00");
        $huffman = self::segment(0xC4, "\x00" . str_repeat("\x01", 16) . "\x05");
        $scan = self::segment(0xDA, "\x01\x01\x00\x00\x3F\x00");
        // Stuffed 0xFF00 and a restart marker are part of the entropy-coded data, not segment boundaries.
        $firstScan = "\x12\xFF\x00\x34\xFF\xD0\x56";
        $secondScan = "\x78\xFF\x00\x9A";
        $jpeg = "\xFF\xD8" . self::jfif() . $frame . $huffman . $scan . $firstScan
            . self::segment(0xFE, 'SECRET-BETWEEN-SCANS')
            . self::segment(0xE1, "Exif\0\0" . ImageBytes::exifTiff(1))
            . $huffman . $scan . $secondScan . "\xFF\xD9" . self::TRAILER;

        $stripped = self::strip($jpeg, FormatName::Jpeg);

        self::assertSame("\xFF\xD8" . self::jfif() . $frame . $huffman . $scan . $firstScan . $huffman . $scan . $secondScan . "\xFF\xD9", $stripped);
    }

    public function testJfifKeepsItsHeaderButNotItsThumbnails(): void
    {
        $withThumbnail = self::segment(0xE0, "JFIF\0\x01\x02\x00\x00\x01\x00\x01" . "\x02\x01" . 'SECRET');
        $extension = self::segment(0xE0, "JFXX\0\x10" . "\xFF\xD8SECRET-JFXX\xFF\xD9");
        $jpeg = self::jpeg();
        $headerEnd = 2 + 18;

        $stripped = self::strip(substr($jpeg, 0, 2) . $withThumbnail . $extension . substr($jpeg, $headerEnd), FormatName::Jpeg);

        self::assertSame(substr($jpeg, 0, 2) . self::jfif() . substr($jpeg, $headerEnd), $stripped);
    }

    public function testJpegDropsEveryApplicationSegmentItDoesNotNeed(): void
    {
        $jpeg = self::jpeg();
        $headerEnd = 2 + 18;
        $icc = self::segment(0xE2, "ICC_PROFILE\0\x01\x01profile");
        $segments = self::segment(0xE1, "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta>SECRET-XMP</x:xmpmeta>")
            . self::segment(0xE2, "MPF\0SECRET-MPF")
            . self::segment(0xED, "Photoshop 3.0\0" . '8BIM' . 'SECRET-IPTC')
            . self::segment(0xEF, 'SECRET-APP15')
            . self::segment(0xFE, "%PDF-1.4\n/JS (app.alert(1))")
            . $icc;

        $kept = self::strip(substr($jpeg, 0, $headerEnd) . $segments . substr($jpeg, $headerEnd), FormatName::Jpeg);
        $dropped = self::strip(substr($jpeg, 0, $headerEnd) . $segments . substr($jpeg, $headerEnd), FormatName::Jpeg, keepColorProfile: false);

        self::assertSame(substr($jpeg, 0, $headerEnd) . $icc . substr($jpeg, $headerEnd), $kept);
        self::assertSame($jpeg, $dropped);
    }

    /** @return iterable<string, array{string, FormatName}> */
    public static function truncated(): iterable
    {
        $jpeg = ImageBytes::jpeg(4, 4);
        yield 'jpeg cut inside the scan' => [substr($jpeg, 0, -2) . "\x12\x34", FormatName::Jpeg];
        yield 'jpeg cut inside a segment' => [substr($jpeg, 0, 10), FormatName::Jpeg];
        yield 'png without IEND' => [substr(ImageBytes::png(4, 4), 0, -12), FormatName::Png];
    }

    #[DataProvider('truncated')]
    public function testTruncatedInputIsRejected(string $bytes, FormatName $format): void
    {
        $this->expectException(CorruptedImageException::class);

        self::strip($bytes, $format);
    }

    public function testPngKeepsImageAndColorChunksAndAnonymizesTheProfileName(): void
    {
        $png = ImageBytes::png(4, 4);
        $headerEnd = 8 + 25;
        $profile = (string) gzcompress(str_pad('SECRET-ICC-PROFILE', 64, "\0"));
        $chunks = ImageBytes::pngChunk('tEXt', "Comment\0SECRET-TEXT")
            . ImageBytes::pngChunk('zTXt', "Comment\0\0" . gzcompress('SECRET-ZTXT'))
            . ImageBytes::pngChunk('iTXt', "XML:com.adobe.xmp\0\0\0\0\0<x:xmpmeta>SECRET-XMP</x:xmpmeta>")
            . ImageBytes::pngChunk('eXIf', ImageBytes::exifTiff(6))
            . ImageBytes::pngChunk('iCCP', "SECRET-ICCNAME\0\0" . $profile)
            . ImageBytes::pngChunk('gAMA', pack('N', 45455))
            . ImageBytes::pngChunk('prVt', 'SECRET-PRIVATE-ANCILLARY');
        $input = substr($png, 0, $headerEnd) . $chunks . substr($png, $headerEnd) . '<?php system($_GET["c"]); ?> SECRET-TRAILER';

        $kept = self::strip($input, FormatName::Png);
        $dropped = self::strip($input, FormatName::Png, keepColorProfile: false);

        $colorChunks = ImageBytes::pngChunk('iCCP', "ICC Profile\0\0" . $profile) . ImageBytes::pngChunk('gAMA', pack('N', 45455));
        self::assertSame(substr($png, 0, $headerEnd) . $colorChunks . substr($png, $headerEnd), $kept);
        self::assertSame($png, $dropped);
    }

    public function testPngWithAnUnknownCriticalChunkIsRejected(): void
    {
        $png = ImageBytes::png(4, 4);
        $headerEnd = 8 + 25;

        $this->expectException(CorruptedImageException::class);

        self::strip(substr($png, 0, $headerEnd) . ImageBytes::pngChunk('SECR', 'payload') . substr($png, $headerEnd), FormatName::Png);
    }

    public function testWebpKeepsOnlyImageChunksAndEndsAtTheRiffSize(): void
    {
        $vp8x = static fn(int $flags): string => ImageBytes::riffChunk('VP8X', chr($flags) . "\0\0\0" . "\x03\0\0" . "\x03\0\0");
        $icc = ImageBytes::riffChunk('ICCP', 'profile');
        $bitstream = ImageBytes::riffChunk('VP8L', "\x2F" . pack('V', 0) . "\0");
        $webp = ImageBytes::riff(
            $vp8x(0x20 | 0x08 | 0x04) . $icc . $bitstream
            . ImageBytes::riffChunk('EXIF', "Exif\0\0" . ImageBytes::exifTiff(6))
            . ImageBytes::riffChunk('XMP ', '<x:xmpmeta>SECRET-XMP</x:xmpmeta>')
            . ImageBytes::riffChunk('SECR', 'SECRET-UNKNOWN-CHUNK'),
        ) . 'SECRET-TRAILER<script>';

        self::assertSame(ImageBytes::riff($vp8x(0x20) . $icc . $bitstream), self::strip($webp, FormatName::Webp));
        self::assertSame(ImageBytes::riff($vp8x(0x00) . $bitstream), self::strip($webp, FormatName::Webp, keepColorProfile: false));
    }

    public function testAnimatedWebpFramesLoseUnknownNestedChunks(): void
    {
        $vp8x = ImageBytes::riffChunk('VP8X', "\x02\0\0\0" . "\x03\0\0" . "\x03\0\0");
        $animation = ImageBytes::riffChunk('ANIM', "\0\0\0\0\0\0");
        $frameHeader = str_repeat("\0", 6) . "\x03\0\0" . "\x03\0\0" . "\x64\0\0" . "\0";
        $bitstream = ImageBytes::riffChunk('VP8L', "\x2F" . pack('V', 0) . "\0");
        $frame = static fn(string $payload): string => ImageBytes::riffChunk('ANMF', $frameHeader . $payload);

        $stripped = self::strip(ImageBytes::riff($vp8x . $animation . $frame($bitstream . ImageBytes::riffChunk('SECR', 'SECRET-IN-FRAME'))), FormatName::Webp);

        self::assertSame(ImageBytes::riff($vp8x . $animation . $frame($bitstream)), $stripped);
    }

    private static function strip(string $bytes, FormatName $format, bool $keepColorProfile = true): string
    {
        return (new MetadataStripper($keepColorProfile))->optimize(new EncodedImage($bytes, $format))->bytes;
    }

    private static function segment(int $marker, string $payload): string
    {
        return "\xFF" . chr($marker) . pack('n', 2 + strlen($payload)) . $payload;
    }

    // The fixture's fill bytes before the frame header are not kept.
    private static function jpeg(): string
    {
        return str_replace("\xFF\xFF\xFF\xC0", "\xFF\xC0", ImageBytes::jpeg(4, 4));
    }

    private static function jfif(): string
    {
        return self::segment(0xE0, "JFIF\0\x01\x02\x00\x00\x01\x00\x01\x00\x00");
    }
}
