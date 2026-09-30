<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

final class InvalidDimensionsException extends InvalidArgumentException
{
    public static function notPositive(int $width, int $height): self
    {
        return new self(sprintf('Dimensions must be positive, got %dx%d.', $width, $height));
    }

    public static function tooLarge(int $width, int $height, int $maxSide): self
    {
        return new self(sprintf('Dimensions %dx%d exceed the representable maximum of %d px per side.', $width, $height, $maxSide));
    }

    public static function sideTooLarge(float $side, int $maxSide): self
    {
        return new self(sprintf('Computed side of %.0f px exceeds the representable maximum of %d px.', $side, $maxSide));
    }

    public static function invalidScaleFactor(float $factor): self
    {
        return new self(sprintf('Scale factor must be a finite number greater than zero, got %s.', self::number($factor)));
    }
}
