<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Blend;

// Separable blend modes as defined by W3C Compositing and Blending Level 1 (non-premultiplied, source-over).
enum BlendMode
{
    case Normal;
    case Multiply;
    case Screen;
    case Overlay;
    case Darken;
    case Lighten;
    case Difference;
    case HardLight;
    case SoftLight;
    case ColorDodge;
    case ColorBurn;
    case Exclusion;

    public function mix(float $backdrop, float $source): float
    {
        return match ($this) {
            self::Normal => $source,
            self::Multiply => $backdrop * $source,
            self::Screen => $backdrop + $source - $backdrop * $source,
            self::Overlay => self::HardLight->mix($source, $backdrop),
            self::Darken => min($backdrop, $source),
            self::Lighten => max($backdrop, $source),
            self::Difference => abs($backdrop - $source),
            self::HardLight => $source <= 0.5
                ? $backdrop * 2 * $source
                : self::Screen->mix($backdrop, 2 * $source - 1),
            self::SoftLight => $source <= 0.5
                ? $backdrop - (1 - 2 * $source) * $backdrop * (1 - $backdrop)
                : $backdrop + (2 * $source - 1) * (($backdrop <= 0.25 ? ((16 * $backdrop - 12) * $backdrop + 4) * $backdrop : sqrt($backdrop)) - $backdrop),
            self::ColorDodge => $source === 1.0 ? 1.0 : min(1.0, $backdrop / (1 - $source)),
            self::ColorBurn => $source === 0.0 ? 0.0 : 1 - min(1.0, (1 - $backdrop) / $source),
            self::Exclusion => $backdrop + $source - 2 * $backdrop * $source,
        };
    }
}
