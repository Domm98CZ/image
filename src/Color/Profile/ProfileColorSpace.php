<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Color\Profile;

enum ProfileColorSpace
{
    case Rgb;
    case Cmyk;
    case Gray;
    case Other;

    public static function fromSignature(string $signature): self
    {
        return match ($signature) {
            'RGB ' => self::Rgb,
            'CMYK' => self::Cmyk,
            'GRAY' => self::Gray,
            default => self::Other,
        };
    }
}
