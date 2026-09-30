<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

// Caller built a value that can never be valid; a bug in calling code, not bad image input.
abstract class InvalidArgumentException extends \InvalidArgumentException implements ImageException
{
    protected static function number(int|float $value): string
    {
        if (is_int($value) || is_finite($value)) {
            return (string) $value;
        }

        // PHP 8.5 warns when NAN/INF is coerced to string; spell them the way PHP prints them itself.
        return is_nan($value) ? 'NAN' : ($value > 0 ? 'INF' : '-INF');
    }
}
