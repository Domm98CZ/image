<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

final class InvalidAngleException extends InvalidArgumentException
{
    public static function notFinite(float $degrees): self
    {
        return new self(sprintf('Angle must be a finite number of degrees, got %s.', self::number($degrees)));
    }
}
