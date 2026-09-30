<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Driver\DriverName;

final class DriverNotAvailableException extends DriverException
{
    public static function none(): self
    {
        return new self('No image driver is available: install ext-gd or ext-imagick.');
    }

    public static function forced(DriverName $driver): self
    {
        return new self(sprintf('The configuration forces driver "%s", but it is not available.', $driver->value));
    }
}
