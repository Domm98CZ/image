<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Metadata;

// Minimal little-endian TIFF with one IFD0 of ASCII tags (e.g. Artist, Copyright).
final readonly class ExifWriter
{
    public const TAG_ARTIST = 0x013B;
    public const TAG_COPYRIGHT = 0x8298;
    private const TYPE_ASCII = 2;

    /** @param array<int, string> $asciiTags tag => value */
    public function write(array $asciiTags): string
    {
        ksort($asciiTags);
        $entryCount = count($asciiTags);
        $dataOffset = 8 + 2 + 12 * $entryCount + 4;
        $entries = '';
        $data = '';
        foreach ($asciiTags as $tag => $value) {
            $value .= "\0";
            $length = strlen($value);
            if ($length <= 4) {
                $entries .= pack('vvV', $tag, self::TYPE_ASCII, $length) . str_pad($value, 4, "\0");
                continue;
            }
            $entries .= pack('vvVV', $tag, self::TYPE_ASCII, $length, $dataOffset + strlen($data));
            $data .= $value . (strlen($value) % 2 === 1 ? "\0" : '');
        }

        return "II*\0" . pack('V', 8) . pack('v', $entryCount) . $entries . pack('V', 0) . $data;
    }
}
