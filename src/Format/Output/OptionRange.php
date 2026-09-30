<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Output;

use Domm98CZ\Image\Exception\InvalidEncodingOptionException;

final class OptionRange
{
    public static function assert(string $option, int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw InvalidEncodingOptionException::outOfRange($option, $value, $min, $max);
        }
    }
}
