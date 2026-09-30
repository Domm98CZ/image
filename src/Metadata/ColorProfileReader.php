<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Metadata;

use Domm98CZ\Image\Color\Profile\ColorProfile;
use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;

// Finds the embedded ICC profile so the planner can route the decode to a driver that honours it.
final readonly class ColorProfileReader
{
    // Real profiles are a few KB (large LUT profiles ~1 MB); anything bigger is not worth inflating.
    private const MAX_PROFILE_BYTES = 4 * 1024 * 1024;
    private const JPEG_ICC_PREFIX = "ICC_PROFILE\0";
    private const INFLATE_STEP = 1024;
    private const AVIF_CONTAINERS = ['meta' => 4, 'iprp' => 0, 'ipco' => 0];
    private const AVIF_MAX_DEPTH = 4;
    private const AVIF_MAX_BOXES = 10_000;

    // Broken or oversized profiles are ignored, exactly like the drivers ignore them, so decoding still works.
    public function read(BinaryString $input, FormatName $format): ?ColorProfile
    {
        try {
            $bytes = match ($format) {
                FormatName::Jpeg => $this->jpeg($input),
                FormatName::Png => $this->png($input),
                FormatName::Webp => $this->webp($input),
                FormatName::Avif => $this->avif($input, 0, $input->length(), 0),
                FormatName::Gif, FormatName::Heic => null,
            };
        } catch (CorruptedImageException) {
            return null;
        }

        return $bytes === null || strlen($bytes) > self::MAX_PROFILE_BYTES ? null : ColorProfile::tryParse($bytes);
    }

    // The profile may be split over several APP2 segments, numbered from 1.
    private function jpeg(BinaryString $input): ?string
    {
        $chunks = [];
        $expected = 0;
        $offset = 2;
        while ($input->has($offset, 2) && $input->uint8($offset) === 0xFF) {
            $marker = $input->uint8($offset + 1);
            if ($marker === 0xFF) {
                ++$offset;
                continue;
            }
            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }
            $length = $input->uint16BigEndian($offset + 2);
            if ($marker === 0xE2 && $length >= 2 + strlen(self::JPEG_ICC_PREFIX) + 2 && $input->matchesAt($offset + 4, self::JPEG_ICC_PREFIX)) {
                $header = $offset + 4 + strlen(self::JPEG_ICC_PREFIX);
                $expected = $input->uint8($header + 1);
                $chunks[$input->uint8($header)] = $input->slice($header + 2, $length - 2 - strlen(self::JPEG_ICC_PREFIX) - 2);
            }
            $offset += 2 + $length;
        }
        if ($expected === 0 || count($chunks) !== $expected) {
            return null;
        }
        ksort($chunks);

        return array_keys($chunks) === range(1, $expected) ? implode('', $chunks) : null;
    }

    private function png(BinaryString $input): ?string
    {
        for ($offset = 8; $input->has($offset, 8); $offset += 12 + $length) {
            $length = $input->uint32BigEndian($offset);
            $type = $input->slice($offset + 4, 4);
            if ($type === 'iCCP') {
                $data = $input->slice($offset + 8, $length);
                $nameEnd = strpos($data, "\0");

                // Profile name, NUL, compression method (0 = zlib), compressed profile.
                return $nameEnd === false || ($data[$nameEnd + 1] ?? '') !== "\0" ? null : $this->inflate(substr($data, $nameEnd + 2));
            }
            if ($type === 'IDAT' || $type === 'IEND') {
                return null;
            }
        }

        return null;
    }

    // Inflates in small steps so a compression bomb stops within a step of the cap.
    private function inflate(string $compressed): ?string
    {
        if (!function_exists('inflate_init')) {
            return null;
        }
        $error = false;
        set_error_handler(static function () use (&$error): bool {
            $error = true;

            return true;
        });
        try {
            $context = inflate_init(ZLIB_ENCODING_DEFLATE);
            if ($context === false) {
                return null;
            }
            $output = '';
            foreach (str_split($compressed, self::INFLATE_STEP) as $step) {
                $output .= (string) inflate_add($context, $step, ZLIB_NO_FLUSH);
                if ($error || strlen($output) > self::MAX_PROFILE_BYTES) {
                    return null;
                }
            }
            $output .= (string) inflate_add($context, '', ZLIB_FINISH);
        } finally {
            restore_error_handler();
        }

        return $error || strlen($output) > self::MAX_PROFILE_BYTES ? null : $output;
    }

    private function webp(BinaryString $input): ?string
    {
        for ($offset = 12; $input->has($offset, 8); $offset += 8 + $size + ($size & 1)) {
            $size = $input->uint32LittleEndian($offset + 4);
            if ($input->matchesAt($offset, 'ICCP')) {
                return $input->slice($offset + 8, $size);
            }
        }

        return null;
    }

    // colr boxes of type "prof"/"rICC" carry an ICC profile; "nclx" (coded primaries) is left to the decoder.
    private function avif(BinaryString $input, int $offset, int $end, int $depth, int &$boxes = 0): ?string
    {
        while ($offset + 8 <= $end) {
            if (++$boxes > self::AVIF_MAX_BOXES) {
                return null;
            }
            $size = $input->uint32BigEndian($offset);
            $type = $input->slice($offset + 4, 4);
            $headerLength = $size === 1 ? 16 : 8;
            $size = match ($size) {
                0 => $end - $offset,
                1 => $input->uint64BigEndian($offset + 8),
                default => $size,
            };
            if ($size < $headerLength || $size > $end - $offset) {
                return null;
            }
            $body = $offset + $headerLength;
            if (array_key_exists($type, self::AVIF_CONTAINERS) && $depth < self::AVIF_MAX_DEPTH) {
                $found = $this->avif($input, $body + self::AVIF_CONTAINERS[$type], $offset + $size, $depth + 1, $boxes);
                if ($found !== null) {
                    return $found;
                }
            } elseif ($type === 'colr' && $size - $headerLength > 4 && in_array($input->slice($body, 4), ['prof', 'rICC'], true)) {
                return $input->slice($body + 4, $size - $headerLength - 4);
            }
            $offset += $size;
        }

        return null;
    }
}
