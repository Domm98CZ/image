<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Support;

// Synthetic sfnt files with hand-built cmap subtables: what the parser reads, not what any installed font happens to contain.
final class SfntFixtures
{
    /** @param array<string, string> $tables tag => table bytes */
    public static function sfnt(array $tables, int $fileOffset = 0): string
    {
        $count = count($tables);
        $directory = '';
        $body = '';
        $dataStart = $fileOffset + 12 + 16 * $count;
        foreach ($tables as $tag => $bytes) {
            $directory .= $tag . pack('NNN', 0, $dataStart + strlen($body), strlen($bytes));
            $body .= $bytes . str_repeat("\0", (4 - strlen($bytes) % 4) % 4);
        }

        return pack('N', 0x00010000) . pack('nnnn', $count, 0, 0, 0) . $directory . $body;
    }

    /** @param list<array{int, int, string}> $subtables platform, encoding, subtable bytes */
    public static function cmap(array $subtables): string
    {
        $records = '';
        $body = '';
        $dataStart = 4 + 8 * count($subtables);
        foreach ($subtables as [$platform, $encoding, $bytes]) {
            $records .= pack('nnN', $platform, $encoding, $dataStart + strlen($body));
            $body .= $bytes;
        }

        return pack('nn', 0, count($subtables)) . $records . $body;
    }

    /** @param list<array{int, int, int}> $groups start code, end code, start glyph */
    public static function format12(array $groups): string
    {
        $body = '';
        foreach ($groups as [$start, $end, $glyph]) {
            $body .= pack('NNN', $start, $end, $glyph);
        }

        return pack('nnNNN', 12, 0, 16 + strlen($body), 0, count($groups)) . $body;
    }

    /** @param list<array{int, int, int, list<int>|null}> $segments start, end, delta, glyph ids for a range-offset segment */
    public static function format4(array $segments): string
    {
        $segments[] = [0xFFFF, 0xFFFF, 1, null];
        $count = count($segments);
        $ends = $starts = $deltas = $offsets = $glyphIds = '';
        foreach ($segments as $index => [$start, $end, $delta, $ids]) {
            $ends .= pack('n', $end);
            $starts .= pack('n', $start);
            $deltas .= pack('n', $delta & 0xFFFF);
            if ($ids === null) {
                $offsets .= pack('n', 0);
                continue;
            }
            // Offset from this idRangeOffset entry to the glyph ids: the rest of the array, then what is already stored.
            $offsets .= pack('n', 2 * ($count - $index) + strlen($glyphIds));
            $glyphIds .= pack('n*', ...$ids);
        }
        $body = pack('nnnn', 2 * $count, 0, 0, 0) . $ends . pack('n', 0) . $starts . $deltas . $offsets . $glyphIds;

        return pack('nnn', 4, 14 + strlen($body), 0) . $body;
    }

    /** A Windows Unicode font file whose format 12 cmap maps exactly these inclusive code point ranges. @param list<array{int, int}> $ranges */
    public static function fontCovering(array $ranges): string
    {
        $glyph = 1;
        $groups = [];
        foreach ($ranges as [$start, $end]) {
            $groups[] = [$start, $end, $glyph];
            $glyph += $end - $start + 1;
        }

        return self::sfnt(['cmap' => self::cmap([[3, 10, self::format12($groups)]])]);
    }
}
