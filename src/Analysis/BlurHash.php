<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Analysis;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Operation\ColorAdjustment;

// The blurhash.org format: https://github.com/woltapp/blurhash. Alpha is not part of the spec and is ignored.
final class BlurHash
{
    private const CHARACTERS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz#$%*+,-.:;=?@[]^_{|}~';

    public static function encode(PixelBuffer $pixels, int $componentsX = 4, int $componentsY = 3): string
    {
        ColorAdjustment::assertRange('BlurHash', 'componentsX', $componentsX, 1, 9);
        ColorAdjustment::assertRange('BlurHash', 'componentsY', $componentsY, 1, 9);

        $factors = self::factors($pixels, $componentsX, $componentsY);
        $ac = array_slice($factors, 1);

        $hash = self::base83Encode(($componentsX - 1) + ($componentsY - 1) * 9, 1);

        if ($ac === []) {
            $hash .= self::base83Encode(0, 1);
            $maximumValue = 1.0;
        } else {
            $largest = 0.0;
            foreach ($ac as $factor) {
                $largest = max($largest, abs($factor[0]), abs($factor[1]), abs($factor[2]));
            }
            $quantisedMaximum = (int) max(0, min(82, floor($largest * 166 - 0.5)));
            $hash .= self::base83Encode($quantisedMaximum, 1);
            $maximumValue = ($quantisedMaximum + 1) / 166;
        }

        $hash .= self::base83Encode(self::encodeDc($factors[0]), 4);
        foreach ($ac as $factor) {
            $hash .= self::base83Encode(self::encodeAc($factor, $maximumValue), 2);
        }

        return $hash;
    }

    /**
     * @return list<array{0:float,1:float,2:float}> row-major, y outer/x inner; index 0 is the DC term
     */
    private static function factors(PixelBuffer $pixels, int $componentsX, int $componentsY): array
    {
        $dimensions = $pixels->dimensions;
        $bytes = $pixels->bytes;
        $scale = 1 / ($dimensions->width * $dimensions->height);
        $factors = [];
        for ($componentY = 0; $componentY < $componentsY; ++$componentY) {
            for ($componentX = 0; $componentX < $componentsX; ++$componentX) {
                $normalisation = ($componentX === 0 && $componentY === 0) ? 1 : 2;
                $totals = [0.0, 0.0, 0.0];
                for ($y = 0; $y < $dimensions->height; ++$y) {
                    $rowBasis = cos(M_PI * $componentY * $y / $dimensions->height);
                    $row = $y * $dimensions->width;
                    for ($x = 0; $x < $dimensions->width; ++$x) {
                        $basis = $normalisation * cos(M_PI * $componentX * $x / $dimensions->width) * $rowBasis;
                        $offset = ($row + $x) * PixelBuffer::BYTES_PER_PIXEL;
                        $totals[0] += $basis * self::sRgbToLinear(ord($bytes[$offset]));
                        $totals[1] += $basis * self::sRgbToLinear(ord($bytes[$offset + 1]));
                        $totals[2] += $basis * self::sRgbToLinear(ord($bytes[$offset + 2]));
                    }
                }
                $factors[] = [$totals[0] * $scale, $totals[1] * $scale, $totals[2] * $scale];
            }
        }

        return $factors;
    }

    /** @param array{0:float,1:float,2:float} $dc */
    private static function encodeDc(array $dc): int
    {
        return (self::linearToSRgb($dc[0]) << 16) + (self::linearToSRgb($dc[1]) << 8) + self::linearToSRgb($dc[2]);
    }

    /** @param array{0:float,1:float,2:float} $ac */
    private static function encodeAc(array $ac, float $maximumValue): int
    {
        $quantR = self::quantiseAcChannel($ac[0], $maximumValue);
        $quantG = self::quantiseAcChannel($ac[1], $maximumValue);
        $quantB = self::quantiseAcChannel($ac[2], $maximumValue);

        return $quantR * 19 * 19 + $quantG * 19 + $quantB;
    }

    private static function quantiseAcChannel(float $value, float $maximumValue): int
    {
        $signed = self::signPow($value / $maximumValue, 0.5);

        return (int) max(0, min(18, floor($signed * 9 + 9.5)));
    }

    private static function signPow(float $value, float $exponent): float
    {
        return ($value <=> 0) * abs($value) ** $exponent;
    }

    private static function sRgbToLinear(int $value): float
    {
        $normalised = $value / 255;

        return $normalised <= 0.04045 ? $normalised / 12.92 : (($normalised + 0.055) / 1.055) ** 2.4;
    }

    private static function linearToSRgb(float $value): int
    {
        $clamped = max(0.0, min(1.0, $value));

        return $clamped <= 0.0031308
            ? (int) round($clamped * 12.92 * 255)
            : (int) round((1.055 * $clamped ** (1 / 2.4) - 0.055) * 255);
    }

    private static function base83Encode(int $value, int $length): string
    {
        $result = '';
        for ($i = 1; $i <= $length; ++$i) {
            $result .= self::CHARACTERS[intdiv($value, 83 ** ($length - $i)) % 83];
        }

        return $result;
    }
}
