<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Drawing;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;

/**
 * @internal Code points a font file maps to a real glyph, read from its cmap table. Drivers swap every other code point
 * for U+FFFD so a missing glyph looks the same everywhere: FreeType (GD) paints the font's .notdef box, ImageMagick paints nothing.
 */
final class FontCoverage
{
    public const REPLACEMENT_CHARACTER = "\u{FFFD}";
    private const FALLBACK_CHARACTER = '?';
    private const CACHE_SIZE = 32;
    private const WINDOWS_PLATFORM = 3;
    private const UNICODE_PLATFORM = 0;
    private const WINDOWS_UNICODE_ENCODINGS = [1, 10];
    private const TABLE_RECORD_SIZE = 16;
    private const OFFSET_TABLE_SIZE = 12;

    /** @var array<string, self> */
    private static array $cache = [];

    /** @param list<array{int, int}>|null $ranges inclusive, sorted, disjoint; null when the file has no readable Unicode cmap */
    private function __construct(
        private readonly ?array $ranges,
    ) {}

    public static function of(Font $font): self
    {
        $key = $font->path . '|' . (int) @filesize($font->path) . '|' . (int) @filemtime($font->path);
        if (!isset(self::$cache[$key])) {
            if (count(self::$cache) >= self::CACHE_SIZE) {
                array_shift(self::$cache);
            }
            self::$cache[$key] = new self(self::parse($font->path));
        }

        return self::$cache[$key];
    }

    public function isKnown(): bool
    {
        return $this->ranges !== null;
    }

    // Unknown coverage counts as covered: the text then reaches the driver untouched.
    public function covers(int $codePoint): bool
    {
        if ($this->ranges === null) {
            return true;
        }
        $low = 0;
        $high = count($this->ranges) - 1;
        while ($low <= $high) {
            $middle = ($low + $high) >> 1;
            [$start, $end] = $this->ranges[$middle];
            if ($codePoint < $start) {
                $high = $middle - 1;
            } elseif ($codePoint > $end) {
                $low = $middle + 1;
            } else {
                return true;
            }
        }

        return false;
    }

    // U+FFFD when the font has it, else a question mark, else nothing stands in for a missing glyph.
    public function placeholder(): ?string
    {
        return match (true) {
            $this->covers(0xFFFD) => self::REPLACEMENT_CHARACTER,
            $this->covers(ord(self::FALLBACK_CHARACTER)) => self::FALLBACK_CHARACTER,
            default => null,
        };
    }

    // Control and format characters (newline, zero-width joiners...) are layout, not glyphs, and stay as they are.
    public function substitute(string $text): string
    {
        if ($this->ranges === null) {
            return $text;
        }
        $replacement = $this->placeholder();
        if ($replacement === null) {
            return $text;
        }
        $result = '';
        foreach (mb_str_split($text, 1, 'UTF-8') as $character) {
            $result .= $this->covers(mb_ord($character, 'UTF-8')) || preg_match('/^[\p{Cc}\p{Cf}]$/u', $character) === 1 ? $character : $replacement;
        }

        return $result;
    }

    /** @return list<array{int, int}>|null */
    private static function parse(string $path): ?array
    {
        try {
            $header = new BinaryString(self::read($path, 0, 16));
            // A collection (.ttc) is judged by its first face; the others share its cmap in practice.
            $offsetTable = $header->matchesAt(0, 'ttcf') ? $header->uint32BigEndian(12) : 0;
            $tableCount = (new BinaryString(self::read($path, $offsetTable, self::OFFSET_TABLE_SIZE)))->uint16BigEndian(4);
            $directory = new BinaryString(self::read($path, $offsetTable + self::OFFSET_TABLE_SIZE, $tableCount * self::TABLE_RECORD_SIZE));
            for ($table = 0; $table < $tableCount; ++$table) {
                $record = $table * self::TABLE_RECORD_SIZE;
                if ($directory->matchesAt($record, 'cmap')) {
                    return self::parseCmap(new BinaryString(self::read($path, $directory->uint32BigEndian($record + 8), $directory->uint32BigEndian($record + 12))));
                }
            }
        } catch (CorruptedImageException) {
        }

        return null;
    }

    /** @return list<array{int, int}>|null */
    private static function parseCmap(BinaryString $cmap): ?array
    {
        $ranges = [];
        $found = false;
        $subtableCount = $cmap->uint16BigEndian(2);
        for ($index = 0; $index < $subtableCount; ++$index) {
            $record = 4 + 8 * $index;
            $platform = $cmap->uint16BigEndian($record);
            $encoding = $cmap->uint16BigEndian($record + 2);
            $unicode = $platform === self::UNICODE_PLATFORM || ($platform === self::WINDOWS_PLATFORM && in_array($encoding, self::WINDOWS_UNICODE_ENCODINGS, true));
            if (!$unicode) {
                continue;
            }
            $subtable = $cmap->uint32BigEndian($record + 4);
            $parsed = match ($cmap->uint16BigEndian($subtable)) {
                4 => self::parseFormat4($cmap, $subtable),
                12 => self::parseFormat12($cmap, $subtable),
                default => null,
            };
            if ($parsed !== null) {
                $found = true;
                array_push($ranges, ...$parsed);
            }
        }

        return $found ? self::merge($ranges) : null;
    }

    /** @return list<array{int, int}> */
    private static function parseFormat4(BinaryString $cmap, int $subtable): array
    {
        $segmentCount = $cmap->uint16BigEndian($subtable + 6) >> 1;
        $endCodes = $subtable + 14;
        $startCodes = $endCodes + 2 * $segmentCount + 2;
        $deltas = $startCodes + 2 * $segmentCount;
        $rangeOffsets = $deltas + 2 * $segmentCount;
        $ranges = [];
        for ($segment = 0; $segment < $segmentCount; ++$segment) {
            $start = $cmap->uint16BigEndian($startCodes + 2 * $segment);
            $end = $cmap->uint16BigEndian($endCodes + 2 * $segment);
            if ($start > $end || $start === 0xFFFF) {
                continue;
            }
            $delta = $cmap->uint16BigEndian($deltas + 2 * $segment);
            $rangeOffset = $cmap->uint16BigEndian($rangeOffsets + 2 * $segment);
            if ($rangeOffset === 0) {
                // glyph = (code + delta) mod 65536: exactly one code in the segment can land on glyph 0.
                $missing = (0x10000 - $delta) & 0xFFFF;
                array_push($ranges, ...self::without([$start, $end], $missing));
                continue;
            }
            $glyphIds = $rangeOffsets + 2 * $segment + $rangeOffset;
            /** @var array<int, int> $ids */
            $ids = unpack('n*', $cmap->slice($glyphIds, 2 * ($end - $start + 1)));
            $runStart = null;
            foreach (array_values($ids) as $offset => $glyph) {
                $code = $start + $offset;
                $mapped = $glyph !== 0 && (($glyph + $delta) & 0xFFFF) !== 0;
                if ($mapped && $runStart === null) {
                    $runStart = $code;
                } elseif (!$mapped && $runStart !== null) {
                    $ranges[] = [$runStart, $code - 1];
                    $runStart = null;
                }
            }
            if ($runStart !== null) {
                $ranges[] = [$runStart, $end];
            }
        }

        return $ranges;
    }

    /** @return list<array{int, int}> */
    private static function parseFormat12(BinaryString $cmap, int $subtable): array
    {
        $groupCount = $cmap->uint32BigEndian($subtable + 12);
        $ranges = [];
        for ($group = 0; $group < $groupCount; ++$group) {
            $record = $subtable + 16 + 12 * $group;
            $start = $cmap->uint32BigEndian($record);
            $end = $cmap->uint32BigEndian($record + 4);
            // Glyph ids run consecutively from the first one, so only a group starting at glyph 0 has a missing code.
            if ($cmap->uint32BigEndian($record + 8) === 0) {
                ++$start;
            }
            if ($start <= $end) {
                $ranges[] = [$start, $end];
            }
        }

        return $ranges;
    }

    /**
     * @param array{int, int} $range
     * @return list<array{int, int}>
     */
    private static function without(array $range, int $code): array
    {
        [$start, $end] = $range;
        if ($code < $start || $code > $end) {
            return [$range];
        }
        $pieces = [];
        if ($code > $start) {
            $pieces[] = [$start, $code - 1];
        }
        if ($code < $end) {
            $pieces[] = [$code + 1, $end];
        }

        return $pieces;
    }

    /**
     * @param list<array{int, int}> $ranges
     * @return list<array{int, int}>
     */
    private static function merge(array $ranges): array
    {
        usort($ranges, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($ranges as [$start, $end]) {
            $last = count($merged) - 1;
            if ($last >= 0 && $start <= $merged[$last][1] + 1) {
                $merged[$last] = [$merged[$last][0], max($merged[$last][1], $end)];
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    private static function read(string $path, int $offset, int $length): string
    {
        if ($length <= 0) {
            throw CorruptedImageException::truncated($offset, $length, 0);
        }
        $bytes = @file_get_contents($path, false, null, $offset, $length);
        if ($bytes === false || strlen($bytes) !== $length) {
            throw CorruptedImageException::truncated($offset, $length, $bytes === false ? 0 : strlen($bytes));
        }

        return $bytes;
    }
}
