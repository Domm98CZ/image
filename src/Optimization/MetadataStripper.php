<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Optimization;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;

// Drops metadata from an encoded file without re-encoding it; pixels and (by default) color management stay.
final readonly class MetadataStripper implements OptimizerInterface
{
    // PNG chunks that describe how to render colors; everything else ancillary is metadata.
    private const PNG_COLOR_CHUNKS = ['iCCP', 'sRGB', 'gAMA', 'cHRM', 'cICP'];
    private const PNG_REQUIRED_OR_RENDERING = ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'acTL', 'fcTL', 'fdAT'];
    private const JPEG_ICC = 0xE2;
    // APP14 "Adobe" tells decoders how to interpret CMYK/YCCK data; dropping it changes colors.
    private const JPEG_ADOBE = 0xEE;

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
            if ($marker === 0xDA || $marker === 0xD9 || ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                return $kept . $input->slice($offset, $input->length() - $offset);
            }
            $length = $input->uint16BigEndian($offset + 2);
            $isMetadata = ($marker >= 0xE1 && $marker <= 0xEF && $marker !== self::JPEG_ADOBE && !($marker === self::JPEG_ICC && $this->keepColorProfile))
                || $marker === 0xFE;
            if (!$isMetadata) {
                $kept .= $input->slice($offset, 2 + $length);
            }
            $offset += 2 + $length;
        }
    }

    private function png(BinaryString $input): string
    {
        $kept = $input->slice(0, 8);
        for ($offset = 8; $input->has($offset, 12); $offset += 12 + $length) {
            $length = $input->uint32BigEndian($offset);
            $type = $input->slice($offset + 4, 4);
            $isColor = in_array($type, self::PNG_COLOR_CHUNKS, true);
            // Critical chunks start with an uppercase letter; unknown critical chunks must never be dropped.
            $isCritical = ctype_upper($type[0]);
            if ($isCritical || in_array($type, self::PNG_REQUIRED_OR_RENDERING, true) || ($isColor && $this->keepColorProfile)) {
                $kept .= $input->slice($offset, 12 + $length);
            }
            if ($type === 'IEND') {
                break;
            }
        }

        return $kept;
    }

    private function webp(BinaryString $input): string
    {
        $end = min($input->length(), $input->uint32LittleEndian(4) + 8);
        $body = 'WEBP';
        for ($offset = 12; $offset + 8 <= $end; $offset += 8 + $size + ($size & 1)) {
            $size = $input->uint32LittleEndian($offset + 4);
            $fourCc = $input->slice($offset, 4);
            if ($fourCc === 'EXIF' || $fourCc === 'XMP ' || ($fourCc === 'ICCP' && !$this->keepColorProfile)) {
                continue;
            }
            $chunk = $input->slice($offset, min(8 + $size + ($size & 1), $end - $offset));
            if ($fourCc === 'VP8X') {
                // Clear the EXIF (0x08) and XMP (0x04) flags, and ICC (0x20) when the profile goes too.
                $chunk[8] = chr(ord($chunk[8]) & ~(0x0C | ($this->keepColorProfile ? 0 : 0x20)));
            }
            $body .= $chunk;
        }

        return 'RIFF' . pack('V', strlen($body)) . $body;
    }
}
