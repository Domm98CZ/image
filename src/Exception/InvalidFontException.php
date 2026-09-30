<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

final class InvalidFontException extends InvalidArgumentException
{
    public static function notReadable(string $path): self
    {
        return new self(sprintf('Font file "%s" is not a readable regular file.', $path));
    }

    public static function notAFont(string $path): self
    {
        return new self(sprintf('File "%s" is not a TrueType or OpenType font.', $path));
    }

    public static function invalidSize(float $size): self
    {
        return new self(sprintf('Font size must be within 1..1000 px, got %s.', self::number($size)));
    }
}
