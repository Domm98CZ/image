<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

final class InvalidAnimationException extends InvalidArgumentException
{
    public static function noFrames(): self
    {
        return new self('An animation needs at least one frame.');
    }

    public static function frameSizeMismatch(int $index, string $expected, string $actual): self
    {
        return new self(sprintf('Frame %d is %s, but every frame of this animation must be %s.', $index, $actual, $expected));
    }

    public static function outOfRange(string $parameter, int $value, string $range): self
    {
        return new self(sprintf('Animation %s must be within %s, got %d.', $parameter, $range, $value));
    }
}
