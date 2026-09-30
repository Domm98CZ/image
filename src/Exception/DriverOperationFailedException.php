<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Driver\DriverName;

final class DriverOperationFailedException extends DriverException
{
    public static function because(DriverName $driver, string $action, ?string $reason = null): self
    {
        return new self(sprintf('Driver "%s" failed to %s%s', $driver->value, $action, $reason === null ? '.' : ': ' . $reason));
    }
}
