<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Metadata;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;

// Replaces EXIF/XMP/IPTC blocks in an encoded file without touching the image data.
final readonly class MetadataEmbedder
{
    public const XMP_JPEG_PREFIX = "http://ns.adobe.com/xap/1.0/\0";
    public const EXIF_JPEG_PREFIX = "Exif\0\0";
    public const IPTC_JPEG_PREFIX = "Photoshop 3.0\0";
    public const XMP_PNG_KEYWORD = 'XML:com.adobe.xmp';
    private const MAX_JPEG_SEGMENT_PAYLOAD = 65_533;

    public function embed(EncodedImage $image, MetadataSet $metadata): EncodedImage
    {
        $bytes = match ($image->format) {
            FormatName::Jpeg => $this->jpeg($image->bytes, $metadata),
            FormatName::Png => $this->png($image->bytes, $metadata),
            FormatName::Webp => $this->webp($image->bytes, $metadata),
            FormatName::Gif, FormatName::Avif, FormatName::Heic => throw UnsupportedFormatException::cannotEmbedMetadata($image->format),
        };

        return new EncodedImage($bytes, $image->format);
    }

    private function jpeg(string $bytes, MetadataSet $metadata): string
    {
        $input = new BinaryString($bytes);
        $kept = [];
        $offset = 2;
        while (true) {
            if ($input->uint8($offset) !== 0xFF) {
                throw CorruptedImageException::invalid(FormatName::Jpeg, sprintf('expected a marker at offset %d', $offset));
            }
            // Fill bytes (extra 0xFF) may precede a marker; they carry nothing and are dropped.
            while ($input->uint8($offset + 1) === 0xFF) {
                ++$offset;
            }
            $marker = $input->uint8($offset + 1);
            if ($marker === 0xDA || $marker === 0xD9 || ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                break;
            }
            $length = $input->uint16BigEndian($offset + 2);
            $segment = $input->slice($offset, 2 + $length);
            $body = substr($segment, 4);
            $isReplaced = ($marker === 0xE1 && (str_starts_with($body, self::EXIF_JPEG_PREFIX) || str_starts_with($body, self::XMP_JPEG_PREFIX)))
                || ($marker === 0xED && str_starts_with($body, self::IPTC_JPEG_PREFIX));
            if (!$isReplaced) {
                $kept[] = [$marker, $segment];
            }
            $offset += 2 + $length;
        }

        $new = '';
        if ($metadata->exif !== null) {
            $new .= self::jpegSegment(0xE1, self::EXIF_JPEG_PREFIX . $metadata->exif);
        }
        if ($metadata->xmp !== null) {
            $new .= self::jpegSegment(0xE1, self::XMP_JPEG_PREFIX . $metadata->xmp);
        }
        if ($metadata->iptc !== null) {
            $new .= self::jpegSegment(0xED, self::IPTC_JPEG_PREFIX . $metadata->iptc);
        }

        // JFIF's APP0 must stay first; the new segments follow it (or SOI when there is no APP0).
        $head = '';
        if ($kept !== [] && $kept[0][0] === 0xE0) {
            $head = array_shift($kept)[1];
        }

        return "\xFF\xD8" . $head . $new . implode('', array_column($kept, 1)) . substr($bytes, $offset);
    }

    private static function jpegSegment(int $marker, string $payload): string
    {
        if (strlen($payload) > self::MAX_JPEG_SEGMENT_PAYLOAD) {
            throw CorruptedImageException::invalid(FormatName::Jpeg, 'metadata block exceeds one 64 KB segment');
        }

        return "\xFF" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
    }

    private function png(string $bytes, MetadataSet $metadata): string
    {
        $input = new BinaryString($bytes);
        $chunks = [];
        for ($offset = 8; $input->has($offset, 12); $offset += 12 + $length) {
            $length = $input->uint32BigEndian($offset);
            $type = $input->slice($offset + 4, 4);
            $chunk = $input->slice($offset, 12 + $length);
            $isReplaced = $type === 'eXIf' || ($type === 'iTXt' && str_starts_with(substr($chunk, 8), self::XMP_PNG_KEYWORD . "\0"));
            if (!$isReplaced) {
                $chunks[] = [$type, $chunk];
            }
        }
        if ($chunks === [] || $chunks[0][0] !== 'IHDR') {
            throw CorruptedImageException::invalid(FormatName::Png, 'first chunk is not IHDR');
        }

        $new = '';
        if ($metadata->exif !== null) {
            $new .= self::pngChunk('eXIf', $metadata->exif);
        }
        if ($metadata->xmp !== null) {
            $new .= self::pngChunk('iTXt', self::XMP_PNG_KEYWORD . "\0\0\0\0\0" . $metadata->xmp);
        }
        $ihdr = array_shift($chunks)[1];

        return substr($bytes, 0, 8) . $ihdr . $new . implode('', array_column($chunks, 1));
    }

    private static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private function webp(string $bytes, MetadataSet $metadata): string
    {
        $input = new BinaryString($bytes);
        $end = min($input->length(), $input->uint32LittleEndian(4) + 8);
        $chunks = [];
        for ($offset = 12; $offset + 8 <= $end; $offset += 8 + $size + ($size & 1)) {
            $size = $input->uint32LittleEndian($offset + 4);
            $fourCc = $input->slice($offset, 4);
            if ($fourCc !== 'EXIF' && $fourCc !== 'XMP ') {
                $chunks[] = [$fourCc, $input->slice($offset, min(8 + $size + ($size & 1), $end - $offset))];
            }
        }
        if ($chunks === []) {
            throw CorruptedImageException::invalid(FormatName::Webp, 'no chunks');
        }

        // EXIF (0x08) and XMP (0x04) are announced in the VP8X flags; simple files are upgraded to VP8X.
        $flags = ($metadata->exif !== null ? 0x08 : 0) | ($metadata->xmp !== null ? 0x04 : 0);
        if ($chunks[0][0] === 'VP8X') {
            $vp8x = $chunks[0][1];
            $vp8x[8] = chr((ord($vp8x[8]) & ~0x0C) | $flags);
            $chunks[0][1] = $vp8x;
        } else {
            $header = (new HeaderProbe())->probe($input);
            $alpha = $header->hasAlpha ? 0x10 : 0;
            $canvas = $header->canvas;
            array_unshift($chunks, ['VP8X', 'VP8X' . pack('V', 10) . chr($flags | $alpha) . "\0\0\0"
                . substr(pack('V', $canvas->width - 1), 0, 3) . substr(pack('V', $canvas->height - 1), 0, 3)]);
        }

        $body = 'WEBP' . implode('', array_column($chunks, 1));
        if ($metadata->exif !== null) {
            $body .= self::riffChunk('EXIF', $metadata->exif);
        }
        if ($metadata->xmp !== null) {
            $body .= self::riffChunk('XMP ', $metadata->xmp);
        }

        return 'RIFF' . pack('V', strlen($body)) . $body;
    }

    private static function riffChunk(string $fourCc, string $payload): string
    {
        return $fourCc . pack('V', strlen($payload)) . $payload . (strlen($payload) % 2 === 1 ? "\0" : '');
    }
}
