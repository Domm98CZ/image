<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Metadata;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;

final readonly class ExifOrientationReader
{
    private const EXIF_PREFIX = "Exif\0\0";
    private const ORIENTATION_TAG = 0x0112;
    private const SHORT_TYPE = 3;

    // Broken metadata must not make an otherwise decodable image unusable, so any parse failure means "no rotation".
    public function read(BinaryString $input, FormatName $format): Orientation
    {
        try {
            $tiff = match ($format) {
                FormatName::Jpeg => $this->jpegExif($input),
                FormatName::Png => $this->pngExif($input),
                FormatName::Webp => $this->webpExif($input),
                FormatName::Gif, FormatName::Avif, FormatName::Heic => null,
            };

            return $tiff === null ? Orientation::TopLeft : $this->orientationFromTiff(new BinaryString($tiff));
        } catch (CorruptedImageException) {
            return Orientation::TopLeft;
        }
    }

    private function jpegExif(BinaryString $input): ?string
    {
        $offset = 2;
        while ($input->has($offset, 4) && $input->uint8($offset) === 0xFF) {
            $marker = $input->uint8($offset + 1);
            if ($marker === 0xDA || $marker === 0xD9) {
                return null;
            }
            $length = $input->uint16BigEndian($offset + 2);
            if ($marker === 0xE1 && $input->matchesAt($offset + 4, self::EXIF_PREFIX)) {
                return $input->slice($offset + 4 + strlen(self::EXIF_PREFIX), $length - 2 - strlen(self::EXIF_PREFIX));
            }
            $offset += 2 + $length;
        }

        return null;
    }

    private function pngExif(BinaryString $input): ?string
    {
        for ($offset = 8; $input->has($offset, 8); $offset += 12 + $length) {
            $length = $input->uint32BigEndian($offset);
            $type = $input->slice($offset + 4, 4);
            if ($type === 'eXIf') {
                return $input->slice($offset + 8, $length);
            }
            if ($type === 'IDAT' || $type === 'IEND') {
                return null;
            }
        }

        return null;
    }

    private function webpExif(BinaryString $input): ?string
    {
        for ($offset = 12; $input->has($offset, 8); $offset += 8 + $size + ($size & 1)) {
            $size = $input->uint32LittleEndian($offset + 4);
            if ($input->matchesAt($offset, 'EXIF')) {
                $payload = $input->slice($offset + 8, $size);

                return str_starts_with($payload, self::EXIF_PREFIX) ? substr($payload, strlen(self::EXIF_PREFIX)) : $payload;
            }
        }

        return null;
    }

    private function orientationFromTiff(BinaryString $tiff): Orientation
    {
        $littleEndian = match ($tiff->slice(0, 4)) {
            "II*\0" => true,
            "MM\0*" => false,
            default => null,
        };
        if ($littleEndian === null) {
            return Orientation::TopLeft;
        }
        $uint16 = static fn(int $offset): int => $littleEndian ? $tiff->uint16LittleEndian($offset) : $tiff->uint16BigEndian($offset);
        $uint32 = static fn(int $offset): int => $littleEndian ? $tiff->uint32LittleEndian($offset) : $tiff->uint32BigEndian($offset);

        $directory = $uint32(4);
        $entryCount = $uint16($directory);
        for ($entry = 0; $entry < $entryCount; ++$entry) {
            $entryOffset = $directory + 2 + $entry * 12;
            if ($uint16($entryOffset) === self::ORIENTATION_TAG && $uint16($entryOffset + 2) === self::SHORT_TYPE) {
                return Orientation::tryFrom($uint16($entryOffset + 8)) ?? Orientation::TopLeft;
            }
        }

        return Orientation::TopLeft;
    }
}
