<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Metadata;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;

// Finds the raw EXIF/XMP/IPTC blocks in an encoded file; malformed files simply yield no metadata.
final readonly class MetadataExtractor
{
    public function extract(EncodedImage $image): MetadataSet
    {
        try {
            $input = new BinaryString($image->bytes);

            return match ($image->format) {
                FormatName::Jpeg => $this->jpeg($input),
                FormatName::Png => $this->png($input),
                FormatName::Webp => $this->webp($input),
                FormatName::Gif, FormatName::Avif, FormatName::Heic => new MetadataSet(),
            };
        } catch (CorruptedImageException) {
            return new MetadataSet();
        }
    }

    private function jpeg(BinaryString $input): MetadataSet
    {
        [$xmp, $exif, $iptc] = [null, null, null];
        for ($offset = 2; $input->has($offset, 4) && $input->uint8($offset) === 0xFF; $offset += 2 + $length) {
            while ($input->uint8($offset + 1) === 0xFF) {
                ++$offset;
            }
            $marker = $input->uint8($offset + 1);
            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }
            $length = $input->uint16BigEndian($offset + 2);
            $body = $input->slice($offset + 4, $length - 2);
            if ($marker === 0xE1 && str_starts_with($body, MetadataEmbedder::XMP_JPEG_PREFIX)) {
                $xmp ??= substr($body, strlen(MetadataEmbedder::XMP_JPEG_PREFIX));
            } elseif ($marker === 0xE1 && str_starts_with($body, MetadataEmbedder::EXIF_JPEG_PREFIX)) {
                $exif ??= substr($body, strlen(MetadataEmbedder::EXIF_JPEG_PREFIX));
            } elseif ($marker === 0xED && str_starts_with($body, MetadataEmbedder::IPTC_JPEG_PREFIX)) {
                $iptc ??= substr($body, strlen(MetadataEmbedder::IPTC_JPEG_PREFIX));
            }
        }

        return new MetadataSet($xmp, $exif, $iptc);
    }

    private function png(BinaryString $input): MetadataSet
    {
        [$xmp, $exif] = [null, null];
        $prefix = MetadataEmbedder::XMP_PNG_KEYWORD . "\0";
        for ($offset = 8; $input->has($offset, 12); $offset += 12 + $length) {
            $length = $input->uint32BigEndian($offset);
            $type = $input->slice($offset + 4, 4);
            if ($type === 'eXIf') {
                $exif ??= $input->slice($offset + 8, $length);
            } elseif ($type === 'iTXt' && $input->matchesAt($offset + 8, $prefix)) {
                $data = $input->slice($offset + 8, $length);
                // keyword\0, compression flag, method, language\0, translated keyword\0, text (uncompressed only).
                $parts = explode("\0", substr($data, strlen($prefix) + 2), 3);
                if (ord($data[strlen($prefix)]) === 0 && count($parts) === 3) {
                    $xmp ??= $parts[2];
                }
            } elseif ($type === 'IEND') {
                break;
            }
        }

        return new MetadataSet($xmp, $exif);
    }

    private function webp(BinaryString $input): MetadataSet
    {
        [$xmp, $exif] = [null, null];
        for ($offset = 12; $input->has($offset, 8); $offset += 8 + $size + ($size & 1)) {
            $size = $input->uint32LittleEndian($offset + 4);
            if ($input->matchesAt($offset, 'XMP ')) {
                $xmp ??= $input->slice($offset + 8, $size);
            } elseif ($input->matchesAt($offset, 'EXIF')) {
                $exif ??= $input->slice($offset + 8, $size);
            }
        }

        return new MetadataSet($xmp, $exif);
    }
}
