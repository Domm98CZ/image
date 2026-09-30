<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Analysis;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;

// Difference hash (dHash): a 9x8 grayscale grid, one bit per horizontal neighbour comparison - 64 bits total.
final class PerceptualHash
{
    private const GRID_WIDTH = 9;
    private const GRID_HEIGHT = 8;

    public static function of(PixelBuffer $pixels): string
    {
        $grid = self::grayscaleGrid($pixels);
        $bits = '';
        foreach ($grid as $row) {
            for ($x = 0; $x < self::GRID_WIDTH - 1; ++$x) {
                $bits .= $row[$x] > $row[$x + 1] ? '1' : '0';
            }
        }
        $hex = '';
        foreach (str_split($bits, 4) as $nibble) {
            $hex .= dechex((int) bindec($nibble));
        }

        return $hex;
    }

    public static function hammingDistance(string $first, string $second): int
    {
        if (strlen($first) !== strlen($second)) {
            throw InvalidOperationException::mismatchedHashLength(strlen($first), strlen($second));
        }
        $distance = 0;
        for ($i = 0, $length = strlen($first); $i < $length; ++$i) {
            $distance += self::popcount(hexdec($first[$i]) ^ hexdec($second[$i]));
        }

        return $distance;
    }

    private static function popcount(int $nibble): int
    {
        return ($nibble & 1) + (($nibble >> 1) & 1) + (($nibble >> 2) & 1) + (($nibble >> 3) & 1);
    }

    /** @return list<list<float>> GRID_HEIGHT rows of GRID_WIDTH luma values */
    private static function grayscaleGrid(PixelBuffer $pixels): array
    {
        $dimensions = $pixels->dimensions;
        $bytes = $pixels->bytes;
        $grid = [];
        for ($gridY = 0; $gridY < self::GRID_HEIGHT; ++$gridY) {
            [$top, $bottom] = self::band($gridY, self::GRID_HEIGHT, $dimensions->height);
            $row = [];
            for ($gridX = 0; $gridX < self::GRID_WIDTH; ++$gridX) {
                [$left, $right] = self::band($gridX, self::GRID_WIDTH, $dimensions->width);
                $row[] = self::averageLuma($bytes, $dimensions, $left, $right, $top, $bottom);
            }
            $grid[] = $row;
        }

        return $grid;
    }

    private static function averageLuma(string $bytes, Dimensions $dimensions, int $left, int $right, int $top, int $bottom): float
    {
        $total = 0.0;
        $count = 0;
        for ($y = $top; $y < $bottom; ++$y) {
            $row = $y * $dimensions->width;
            for ($x = $left; $x < $right; ++$x) {
                $offset = ($row + $x) * PixelBuffer::BYTES_PER_PIXEL;
                $total += 0.299 * ord($bytes[$offset]) + 0.587 * ord($bytes[$offset + 1]) + 0.114 * ord($bytes[$offset + 2]);
                ++$count;
            }
        }

        return $count > 0 ? $total / $count : 0.0;
    }

    /** @return array{0:int,1:int} */
    private static function band(int $index, int $divisions, int $length): array
    {
        $top = intdiv($index * $length, $divisions);
        $bottom = intdiv(($index + 1) * $length, $divisions);

        return [$top, max($top + 1, $bottom)];
    }
}
