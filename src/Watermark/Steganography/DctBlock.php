<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark\Steganography;

use Domm98CZ\Image\Geometry\Point;

/** @internal Koch-Zhao style: the sign of luma DCT C(2,1) - C(1,2) carries the bit; only those two are computed. */
final class DctBlock
{
    /** @var array{list<float>, list<float>}|null */
    private static ?array $basis = null;

    public static function embed(string &$rgba, int $width, Point $block, int $bit, float $margin): void
    {
        [$first, $second] = self::basis();
        [$c1, $c2] = self::coefficients($rgba, $width, $block);
        $gap = $bit === 1 ? $c1 - $c2 : $c2 - $c1;
        if ($gap >= $margin) {
            return;
        }
        // Split the missing gap evenly between both coefficients, in opposite directions.
        $shift = ($margin - $gap) / 2 * ($bit === 1 ? 1 : -1);
        for ($y = 0; $y < 8; ++$y) {
            $row = (($block->y + $y) * $width + $block->x) * 4;
            for ($x = 0; $x < 8; ++$x) {
                $delta = $shift * ($first[$y * 8 + $x] - $second[$y * 8 + $x]);
                $offset = $row + $x * 4;
                for ($channel = 0; $channel < 3; ++$channel) {
                    $rgba[$offset + $channel] = chr(max(0, min(255, (int) round(ord($rgba[$offset + $channel]) + $delta))));
                }
            }
        }
    }

    public static function read(string $rgba, int $width, Point $block): int
    {
        [$c1, $c2] = self::coefficients($rgba, $width, $block);

        return $c1 > $c2 ? 1 : 0;
    }

    /** @return array{float, float} */
    private static function coefficients(string $rgba, int $width, Point $block): array
    {
        [$first, $second] = self::basis();
        $c1 = 0.0;
        $c2 = 0.0;
        for ($y = 0; $y < 8; ++$y) {
            $row = (($block->y + $y) * $width + $block->x) * 4;
            for ($x = 0; $x < 8; ++$x) {
                $offset = $row + $x * 4;
                $luma = 0.299 * ord($rgba[$offset]) + 0.587 * ord($rgba[$offset + 1]) + 0.114 * ord($rgba[$offset + 2]);
                $c1 += $luma * $first[$y * 8 + $x];
                $c2 += $luma * $second[$y * 8 + $x];
            }
        }

        return [$c1, $c2];
    }

    // Orthonormal 8x8 DCT-II basis for (u, v) = (2, 1) and (1, 2): mid frequencies JPEG quantizes gently.
    /** @return array{list<float>, list<float>} */
    private static function basis(): array
    {
        return self::$basis ??= [self::function(2, 1), self::function(1, 2)];
    }

    /** @return list<float> */
    private static function function(int $u, int $v): array
    {
        $values = [];
        for ($y = 0; $y < 8; ++$y) {
            for ($x = 0; $x < 8; ++$x) {
                $values[] = 0.25 * cos((2 * $x + 1) * $u * M_PI / 16) * cos((2 * $y + 1) * $v * M_PI / 16);
            }
        }

        return $values;
    }
}
