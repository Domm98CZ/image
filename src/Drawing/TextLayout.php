<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Drawing;

use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Point;

/**
 * @internal The lines of a "\n"-separated text and where each one sits. Left to the renderers, libgd stacks lines
 * 1.05 em apart and ImageMagick by the font's ascender + descender (~1.17 em for DejaVu), so the same paragraph came
 * out taller on one driver; instead every driver draws and measures the lines one by one at this pitch.
 */
final class TextLayout
{
    // Line pitch in em: the usual single-spacing default, above the ascender + descender of common fonts.
    public const LINE_HEIGHT = 1.2;

    public static function pitch(Font $font): float
    {
        return $font->size * self::LINE_HEIGHT;
    }

    /** @return list<string> */
    public static function lines(string $text): array
    {
        return explode("\n", $text);
    }

    // The n-th baseline: the first one moved n pitches along the text's own downward direction, rounded from the origin so no drift accumulates.
    public static function baseline(Point $first, Angle $angle, float $pitch, int $line): Point
    {
        $distance = $line * $pitch;
        $radians = $angle->radians();

        return $first->movedBy((int) round(-sin($radians) * $distance), (int) round(cos($radians) * $distance));
    }

    /** @return list<Text> one shape per non-blank line; a blank line draws nothing but keeps its room */
    public static function shapes(Text $shape): array
    {
        $lines = self::lines($shape->text);
        if (count($lines) === 1) {
            return [$shape];
        }
        $pitch = self::pitch($shape->font);
        $shapes = [];
        foreach ($lines as $index => $line) {
            if ($line === '') {
                continue;
            }
            $shapes[] = new Text($line, self::baseline($shape->baseline, $shape->angle, $pitch, $index), $shape->font, $shape->color, $shape->angle);
        }

        return $shapes;
    }
}
