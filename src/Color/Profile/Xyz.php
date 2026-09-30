<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Color\Profile;

use Domm98CZ\Image\Exception\InvalidColorException;

// A CIE XYZ tristimulus value, as ICC profiles store colorants and white points.
final readonly class Xyz
{
    public function __construct(
        public float $x,
        public float $y,
        public float $z,
    ) {
        foreach ([$x, $y, $z] as $component) {
            if (!is_finite($component) || $component < 0.0 || $component > 2.0) {
                throw InvalidColorException::profileValueOutOfRange('XYZ component', $component, '0..2');
            }
        }
    }

    // The ICC profile connection space white.
    public static function d50(): self
    {
        return new self(0.9642, 1.0, 0.8249);
    }
}
