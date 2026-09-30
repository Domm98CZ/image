<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

final class InvalidColorException extends InvalidArgumentException
{
    public static function channelOutOfRange(string $channel, int|float $value, string $range): self
    {
        return new self(sprintf('Color channel "%s" must be within %s, got %s.', $channel, $range, self::number($value)));
    }

    public static function malformedHex(string $hex): self
    {
        return new self(sprintf('"%s" is not a valid hex color; expected #rgb, #rgba, #rrggbb or #rrggbbaa.', $hex));
    }

    public static function profileValueOutOfRange(string $what, float $value, string $range): self
    {
        return new self(sprintf('Color profile %s must be within %s, got %s.', $what, $range, self::number($value)));
    }
}
