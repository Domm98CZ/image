<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Exception\InvalidOperationException;

/** @internal Shared parameter validation for color operations. */
final class ColorAdjustment
{
    public static function assertRange(string $operation, string $parameter, int|float $value, int|float $min, int|float $max): void
    {
        if (!is_finite((float) $value) || $value < $min || $value > $max) {
            throw InvalidOperationException::outOfRange($operation, $parameter, $value, sprintf('%s..%s', $min, $max));
        }
    }
}
