<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Support;

// Minimal, hand-built headers: enough structure for header probes, no real pixel data.
final class ImageBytes
{
    public static function jpeg(int $width, int $height, int $startOfFrameMarker = 0xC0): string
    {
        $app0 = "\xFF\xE0" . pack('n', 16) . "JFIF\0\x01\x01\0\0\x01\0\x01\0\0";
        $startOfFrame = "\xFF" . chr($startOfFrameMarker) . pack('n', 17) . "\x08" . pack('nn', $height, $width)
            . "\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01";

        return "\xFF\xD8" . $app0 . "\xFF\xFF" . $startOfFrame . "\xFF\xDA" . pack('n', 8) . "\x01\x01\x00\x00\x3F\x00" . "\xFF\xD9";
    }

    public static function png(int $width, int $height, int $colorType = 6, bool $transparencyChunk = false): string
    {
        $bytes = "\x89PNG\r\n\x1A\n" . self::pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, $colorType, 0, 0, 0));
        if ($colorType === 3) {
            $bytes .= self::pngChunk('PLTE', "\0\0\0\xFF\xFF\xFF");
        }
        if ($transparencyChunk) {
            $bytes .= self::pngChunk('tRNS', "\0");
        }

        return $bytes . self::pngChunk('IDAT', (string) gzcompress("\0")) . self::pngChunk('IEND', '');
    }

    /** @param list<array{int, int}> $frames */
    public static function gif(int $screenWidth, int $screenHeight, array $frames, bool $transparent = false, bool $trailer = true): string
    {
        $bytes = 'GIF89a' . pack('vv', $screenWidth, $screenHeight) . "\x80\x00\x00" . "\x00\x00\x00\xFF\xFF\xFF";
        $bytes .= "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";
        foreach ($frames as [$width, $height]) {
            $bytes .= "\x21\xF9\x04" . ($transparent ? "\x01" : "\x00") . "\x0A\x00\x00\x00";
            $bytes .= "\x2C" . pack('vvvv', 0, 0, $width, $height) . "\x80" . "\x00\x00\x00\xFF\xFF\xFF";
            $bytes .= "\x02\x02\x44\x01\x00";
        }

        return $trailer ? $bytes . "\x3B" : $bytes;
    }

    /**
     * Frames: [left, top, width, height, disposal, delayCentiseconds, transparentIndex|null, withLocalTable].
     *
     * @param list<array{int, int, int, int, int, int, ?int, bool}> $frames
     */
    public static function animatedGif(int $screenWidth, int $screenHeight, array $frames, ?int $loopCount = 0, bool $globalTable = true): string
    {
        $table = "\x00\x00\x00\xFF\x00\x00\x00\xFF\x00\x00\x00\xFF";
        $bytes = 'GIF89a' . pack('vv', $screenWidth, $screenHeight) . ($globalTable ? "\x81" : "\x00") . "\x00\x00" . ($globalTable ? $table : '');
        if ($loopCount !== null) {
            $bytes .= "\x21\xFF\x0BNETSCAPE2.0\x03\x01" . pack('v', $loopCount) . "\x00";
        }
        $bytes .= "\x21\xFE\x05hello\x00";
        foreach ($frames as [$left, $top, $width, $height, $disposal, $delay, $transparent, $localTable]) {
            $bytes .= "\x21\xF9\x04" . chr(($disposal << 2) | ($transparent === null ? 0 : 1)) . pack('v', $delay) . chr($transparent ?? 0) . "\x00";
            $bytes .= "\x2C" . pack('vvvv', $left, $top, $width, $height) . ($localTable ? "\x81" . $table : "\x00");
            $bytes .= "\x02\x02\x44\x01\x00";
        }

        return $bytes . "\x3B";
    }

    public static function webpLossy(int $width, int $height): string
    {
        return self::riff(self::riffChunk('VP8 ', "\x00\x00\x00\x9D\x01\x2A" . pack('vv', $width, $height) . str_repeat("\0", 10)));
    }

    public static function webpLossless(int $width, int $height, bool $alpha): string
    {
        $bits = ($width - 1) | (($height - 1) << 14) | (($alpha ? 1 : 0) << 28);

        return self::riff(self::riffChunk('VP8L', "\x2F" . pack('V', $bits) . str_repeat("\0", 5)));
    }

    /** @param list<array{int, int}> $frames */
    public static function webpExtended(int $canvasWidth, int $canvasHeight, bool $alpha, array $frames = []): string
    {
        $flags = ($alpha ? 0x10 : 0) | ($frames !== [] ? 0x02 : 0);
        $vp8x = chr($flags) . "\0\0\0" . self::uint24($canvasWidth - 1) . self::uint24($canvasHeight - 1);
        $chunks = self::riffChunk('VP8X', $vp8x);
        if ($frames === []) {
            return self::riff($chunks . self::riffChunk('VP8L', "\x2F" . pack('V', 0) . "\0"));
        }
        $chunks .= self::riffChunk('ANIM', "\0\0\0\0\0\0");
        foreach ($frames as [$width, $height]) {
            $chunks .= self::riffChunk('ANMF', self::uint24(0) . self::uint24(0) . self::uint24($width - 1) . self::uint24($height - 1) . self::uint24(100) . "\0" . 'odd');
        }

        return self::riff($chunks);
    }

    /** @param list<array{int, int}> $spatialExtents */
    public static function avif(array $spatialExtents, bool $alpha = false, string $majorBrand = 'avif', string $compatibleBrands = 'mif1miaf'): string
    {
        $properties = '';
        foreach ($spatialExtents as [$width, $height]) {
            $properties .= self::box('ispe', "\0\0\0\0" . pack('NN', $width, $height));
        }
        if ($alpha) {
            $properties .= self::box('auxC', "\0\0\0\0urn:mpeg:mpegB:cicp:systems:auxiliary:alpha\0");
        }
        $meta = self::box('meta', "\0\0\0\0" . self::box('hdlr', str_repeat("\0", 8) . 'pict' . str_repeat("\0", 13)) . self::box('iprp', self::box('ipco', $properties)));

        return self::box('ftyp', $majorBrand . "\0\0\0\0" . $compatibleBrands) . $meta . self::box('mdat', 'data');
    }

    // A real, minimal HEVC-coded HEIC file (20x15 solid red), unlike avif() above: HEIC's mdat holds an
    // opaque HEVC bitstream that cannot be hand-built like ISOBMFF box structure, so this is embedded
    // rather than synthesized. Generated once with a temporary libheif x265 encoder plugin (decode-only
    // libde265 is what ships in docker/Dockerfile; this library never encodes HEIC (patent/licensing).
    public static function heic(): string
    {
        return base64_decode(
            'AAAAGGZ0eXBoZWljAAAAAG1pZjFoZWljAAABaG1ldGEAAAAAAAAAIWhkbHIAAAAAAAAAAHBpY3QAAAAA'
            . 'AAAAAAAAAAAAAAAAImlsb2MAAAAAREAAAQABAAAAAAGIAAEAAAAAAAAAKgAAACNpaW5mAAAAAAABAAAA'
            . 'FWluZmUCAAAAAAEAAGh2YzEAAAAADnBpdG0AAAAAAAEAAADoaXBycAAAAMlpcGNvAAAAdWh2Y0MBA3AA'
            . 'AAAAAAAAAAAe8AD8/fj4AAAPA2AAAQAYQAEMAf//A3AAAAMAkAAAAwAAAwAeugJAYQABAClCAQEDcAAA'
            . 'AwCQAAADAAADAB6gIIEFluqumubgIaDAgAAADIAAAAMAhGIAAQAGRAHBc8CJAAAAFGlzcGUAAAAAAAAA'
            . 'QAAAAEAAAAAoY2xhcAAAABQAAAABAAAADwAAAAH////UAAAAAv///88AAAACAAAAEHBpeGkAAAAAAwgI'
            . 'CAAAABdpcG1hAAAAAAAAAAEAAQSBAgSDAAAAMm1kYXQAAAAmKAGvE4D4EPdn/+u8Ff+Vaz/zN7Hpzsgg'
            . 'Js51ipVbO3sAAAMABqQ=',
            true,
        );
    }

    public static function exifTiff(int $orientation, bool $littleEndian = true): string
    {
        $uint16 = $littleEndian ? 'v' : 'n';
        $uint32 = $littleEndian ? 'V' : 'N';
        $entries = pack($uint16 . $uint16 . $uint32, 0x010F, 2, 4) . 'Foo' . "\0"
            . pack($uint16 . $uint16 . $uint32 . $uint16, 0x0112, 3, 1, $orientation) . "\0\0";

        return ($littleEndian ? "II*\0" : "MM\0*") . pack($uint32, 8) . pack($uint16, 2) . $entries . pack($uint32, 0);
    }

    public static function jpegWithExif(int $width, int $height, string $tiff): string
    {
        $app1 = "\xFF\xE1" . pack('n', 2 + 6 + strlen($tiff)) . "Exif\0\0" . $tiff;

        return "\xFF\xD8" . $app1 . substr(self::jpeg($width, $height), 2);
    }

    public static function injectExifIntoJpeg(string $jpeg, string $tiff): string
    {
        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', 2 + 6 + strlen($tiff)) . "Exif\0\0" . $tiff . substr($jpeg, 2);
    }

    public static function pngWithExif(int $width, int $height, string $tiff): string
    {
        $png = self::png($width, $height);
        $afterHeader = 8 + 25;

        return substr($png, 0, $afterHeader) . self::pngChunk('eXIf', $tiff) . substr($png, $afterHeader);
    }

    public static function webpWithExif(int $width, int $height, string $exifPayload): string
    {
        $vp8x = "\x08\0\0\0" . self::uint24($width - 1) . self::uint24($height - 1);

        return self::riff(self::riffChunk('VP8X', $vp8x) . self::riffChunk('VP8L', "\x2F" . pack('V', 0) . "\0") . self::riffChunk('EXIF', $exifPayload));
    }

    /** @param list<array{int, string}> $chunks APP2 segments as [sequence number, profile slice], in file order */
    public static function jpegWithIcc(int $width, int $height, array $chunks, int $declaredCount): string
    {
        $segments = '';
        foreach ($chunks as [$sequence, $slice]) {
            $body = "ICC_PROFILE\0" . chr($sequence) . chr($declaredCount) . $slice;
            $segments .= "\xFF\xE2" . pack('n', 2 + strlen($body)) . $body;
        }

        return "\xFF\xD8" . $segments . substr(self::jpeg($width, $height), 2);
    }

    public static function pngWithIcc(int $width, int $height, string $compressedProfile, string $name = 'icc'): string
    {
        $png = self::png($width, $height);
        $afterHeader = 8 + 25;

        return substr($png, 0, $afterHeader) . self::pngChunk('iCCP', $name . "\0\0" . $compressedProfile) . substr($png, $afterHeader);
    }

    public static function webpWithIcc(int $width, int $height, string $profile): string
    {
        $vp8x = "\x20\0\0\0" . self::uint24($width - 1) . self::uint24($height - 1);

        return self::riff(self::riffChunk('VP8X', $vp8x) . self::riffChunk('ICCP', $profile) . self::riffChunk('VP8L', "\x2F" . pack('V', 0) . "\0"));
    }

    public static function avifWithColr(int $width, int $height, string $colourType, string $payload): string
    {
        $properties = self::box('ispe', "\0\0\0\0" . pack('NN', $width, $height)) . self::box('colr', $colourType . $payload);
        $meta = self::box('meta', "\0\0\0\0" . self::box('hdlr', str_repeat("\0", 8) . 'pict' . str_repeat("\0", 13)) . self::box('iprp', self::box('ipco', $properties)));

        return self::box('ftyp', "avif\0\0\0\0mif1miaf") . $meta . self::box('mdat', 'data');
    }

    public static function box(string $type, string $body): string
    {
        return pack('N', 8 + strlen($body)) . $type . $body;
    }

    private static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private static function riff(string $chunks): string
    {
        return 'RIFF' . pack('V', 4 + strlen($chunks)) . 'WEBP' . $chunks;
    }

    private static function riffChunk(string $fourCc, string $payload): string
    {
        return $fourCc . pack('V', strlen($payload)) . $payload . (strlen($payload) % 2 === 1 ? "\0" : '');
    }

    private static function uint24(int $value): string
    {
        return substr(pack('V', $value), 0, 3);
    }
}
