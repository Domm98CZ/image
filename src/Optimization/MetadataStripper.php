<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Optimization;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;

// Drops metadata from an encoded file without re-encoding it; pixels and (by default) color management stay.
// Chunks and segments are whitelisted: whatever the decoder does not need goes, known or not, and so does
// anything after the end of the image.
final readonly class MetadataStripper implements OptimizerInterface
{
    private const PNG_IMAGE_CHUNKS = ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'acTL', 'fcTL', 'fdAT'];
    private const PNG_COLOR_CHUNKS = ['iCCP', 'sRGB', 'gAMA', 'cHRM', 'cICP'];
    private const PNG_PROFILE_NAME = 'ICC Profile';
    private const PNG_MAX_PROFILE_NAME = 79;
    private const WEBP_IMAGE_CHUNKS = ['VP8 ', 'VP8L', 'ALPH', 'ANIM'];
    private const WEBP_FRAME_CHUNKS = ['VP8 ', 'VP8L', 'ALPH'];
    private const WEBP_FRAME_HEADER = 16;
    private const JPEG_START_OF_SCAN = 0xDA;
    private const JPEG_END_OF_IMAGE = 0xD9;
    private const JPEG_JFIF = 0xE0;
    private const JPEG_ICC = 0xE2;
    // APP14 "Adobe" tells decoders how to interpret CMYK/YCCK data; dropping it changes colors.
    private const JPEG_ADOBE = 0xEE;
    private const JFIF_HEADER = 14;

    public function __construct(
        private bool $keepColorProfile = true,
    ) {}

    public function optimize(EncodedImage $image): EncodedImage
    {
        return new EncodedImage(match ($image->format) {
            FormatName::Jpeg => $this->jpeg(new BinaryString($image->bytes)),
            FormatName::Png => $this->png(new BinaryString($image->bytes)),
            FormatName::Webp => $this->webp(new BinaryString($image->bytes)),
            FormatName::Gif, FormatName::Avif, FormatName::Heic => $image->bytes,
        }, $image->format);
    }

    private function jpeg(BinaryString $input): string
    {
        $kept = "\xFF\xD8";
        $offset = 2;
        while (true) {
            if ($input->uint8($offset) !== 0xFF) {
                throw CorruptedImageException::invalid(FormatName::Jpeg, sprintf('expected a marker at offset %d', $offset));
            }
            while ($input->uint8($offset + 1) === 0xFF) {
                ++$offset;
            }
            $marker = $input->uint8($offset + 1);
            if ($marker === self::JPEG_END_OF_IMAGE) {
                return $kept . "\xFF\xD9";
            }
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $kept .= $input->slice($offset, 2);
                $offset += 2;

                continue;
            }
            if ($marker === 0x00 || $marker === 0xD8) {
                throw CorruptedImageException::invalid(FormatName::Jpeg, sprintf('unexpected marker at offset %d', $offset));
            }
            $length = $input->uint16BigEndian($offset + 2);
            if ($length < 2) {
                throw CorruptedImageException::invalid(FormatName::Jpeg, sprintf('segment length %d at offset %d', $length, $offset));
            }
            $segment = $input->slice($offset, 2 + $length);
            $offset += 2 + $length;
            if ($marker === self::JPEG_START_OF_SCAN) {
                $scanEnd = self::jpegScanEnd($input, $offset);
                $kept .= $segment . $input->slice($offset, $scanEnd - $offset);
                $offset = $scanEnd;

                continue;
            }
            $kept .= $this->jpegSegment($marker, substr($segment, 4));
        }
    }

    // SOFn, DHT, DAC, DQT, DNL, DRI and the hierarchical markers carry image data; of the APPn segments only the
    // JFIF header (without its thumbnail), the ICC profile and Adobe's color transform are needed to decode.
    private function jpegSegment(int $marker, string $payload): string
    {
        $keep = ($marker >= 0xC0 && $marker <= 0xCF)
            || ($marker >= 0xDB && $marker <= 0xDF)
            || ($marker === self::JPEG_ICC && $this->keepColorProfile && str_starts_with($payload, "ICC_PROFILE\0"))
            || ($marker === self::JPEG_ADOBE && str_starts_with($payload, 'Adobe'));
        if ($keep) {
            return "\xFF" . chr($marker) . pack('n', 2 + strlen($payload)) . $payload;
        }
        if ($marker === self::JPEG_JFIF && str_starts_with($payload, "JFIF\0") && strlen($payload) >= self::JFIF_HEADER) {
            return "\xFF\xE0" . pack('n', 2 + self::JFIF_HEADER) . substr($payload, 0, self::JFIF_HEADER - 2) . "\0\0";
        }

        return '';
    }

    // Entropy-coded data runs until a marker other than a stuffed 0xFF00 or a restart marker.
    private static function jpegScanEnd(BinaryString $input, int $offset): int
    {
        $length = $input->length();
        while (true) {
            $position = strpos($input->bytes, "\xFF", $offset);
            if ($position === false || $position + 1 >= $length) {
                throw CorruptedImageException::invalid(FormatName::Jpeg, 'missing end of image marker');
            }
            $next = $input->uint8($position + 1);
            if ($next !== 0x00 && ($next < 0xD0 || $next > 0xD7)) {
                return $position;
            }
            $offset = $position + 2;
        }
    }

    private function png(BinaryString $input): string
    {
        $kept = $input->slice(0, 8);
        for ($offset = 8; $input->has($offset, 12); $offset += 12 + $length) {
            $length = $input->uint32BigEndian($offset);
            $type = $input->slice($offset + 4, 4);
            $chunk = $input->slice($offset, 12 + $length);
            if (in_array($type, self::PNG_IMAGE_CHUNKS, true)) {
                $kept .= $chunk;
            } elseif (in_array($type, self::PNG_COLOR_CHUNKS, true)) {
                if ($this->keepColorProfile) {
                    $kept .= $type === 'iCCP' ? self::anonymousProfile(substr($chunk, 8, $length)) : $chunk;
                }
            } elseif (ctype_upper($type[0])) {
                // Decoders refuse an unknown critical chunk, so without it the file would no longer be the same image.
                throw CorruptedImageException::invalid(FormatName::Png, 'unknown critical chunk');
            }
            if ($type === 'IEND') {
                return $kept;
            }
        }

        throw CorruptedImageException::invalid(FormatName::Png, 'missing IEND chunk');
    }

    // The profile name is free text chosen by whoever embedded it; the compressed profile itself stays untouched.
    private static function anonymousProfile(string $data): string
    {
        $nameEnd = strpos(substr($data, 0, self::PNG_MAX_PROFILE_NAME + 1), "\0");
        if ($nameEnd === false || $nameEnd === 0) {
            return '';
        }
        $anonymous = self::PNG_PROFILE_NAME . substr($data, $nameEnd);

        return pack('N', strlen($anonymous)) . 'iCCP' . $anonymous . pack('N', crc32('iCCP' . $anonymous));
    }

    private function webp(BinaryString $input): string
    {
        $end = min($input->length(), $input->uint32LittleEndian(4) + 8);
        $body = 'WEBP' . $this->webpChunks($input, 12, $end, true);

        return 'RIFF' . pack('V', strlen($body)) . $body;
    }

    private function webpChunks(BinaryString $input, int $offset, int $end, bool $topLevel): string
    {
        $kept = '';
        for (; $offset + 8 <= $end; $offset += 8 + $size + ($size & 1)) {
            $size = $input->uint32LittleEndian($offset + 4);
            $fourCc = $input->slice($offset, 4);
            $chunk = $input->slice($offset, min(8 + $size + ($size & 1), $end - $offset));
            if (!$topLevel) {
                $kept .= in_array($fourCc, self::WEBP_FRAME_CHUNKS, true) ? $chunk : '';
            } elseif ($fourCc === 'ICCP') {
                $kept .= $this->keepColorProfile ? $chunk : '';
            } elseif ($fourCc === 'VP8X' && strlen($chunk) > 8) {
                // Clear the EXIF (0x08) and XMP (0x04) flags, and ICC (0x20) when the profile goes too.
                $chunk[8] = chr(ord($chunk[8]) & ~(0x0C | ($this->keepColorProfile ? 0 : 0x20)));
                $kept .= $chunk;
            } elseif ($fourCc === 'ANMF' && strlen($chunk) >= 8 + self::WEBP_FRAME_HEADER) {
                // A frame nests its own chunks, unknown ones included.
                $frame = substr($chunk, 8, self::WEBP_FRAME_HEADER)
                    . $this->webpChunks($input, $offset + 8 + self::WEBP_FRAME_HEADER, $offset + strlen($chunk), false);
                $kept .= 'ANMF' . pack('V', strlen($frame)) . $frame . (strlen($frame) % 2 === 1 ? "\0" : '');
            } elseif (in_array($fourCc, self::WEBP_IMAGE_CHUNKS, true)) {
                $kept .= $chunk;
            }
        }

        return $kept;
    }
}
