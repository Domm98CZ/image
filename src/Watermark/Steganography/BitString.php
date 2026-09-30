<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark\Steganography;

/** @internal */
final class BitString
{
    /** @return list<int> most significant bit first */
    public static function fromBytes(string $bytes): array
    {
        $bits = [];
        foreach (str_split($bytes) as $byte) {
            $value = ord($byte);
            for ($bit = 7; $bit >= 0; --$bit) {
                $bits[] = ($value >> $bit) & 1;
            }
        }

        return $bits;
    }

    /** @param list<int> $bits */
    public static function toBytes(array $bits): string
    {
        $bytes = '';
        foreach (array_chunk($bits, 8) as $chunk) {
            $value = 0;
            foreach ($chunk as $bit) {
                $value = ($value << 1) | $bit;
            }
            $bytes .= chr($value << (8 - count($chunk)));
        }

        return $bytes;
    }
}
