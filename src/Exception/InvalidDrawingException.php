<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

final class InvalidDrawingException extends InvalidArgumentException
{
    public static function outOfRange(string $shape, string $parameter, int|float $value, string $range): self
    {
        return new self(sprintf('%s: %s must be within %s, got %s.', $shape, $parameter, $range, self::number($value)));
    }

    public static function nothingToPaint(string $shape): self
    {
        return new self(sprintf('%s needs a fill, a stroke or both.', $shape));
    }

    public static function invalidText(string $reason): self
    {
        return new self(sprintf('Text %s.', $reason));
    }
}
