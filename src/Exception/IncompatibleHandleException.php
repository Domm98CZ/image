<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Driver\DriverName;

final class IncompatibleHandleException extends DriverException
{
    public static function belongsTo(DriverName $owner, DriverName $receiver): self
    {
        return new self(sprintf('Image handle belongs to driver "%s" and cannot be used by driver "%s".', $owner->value, $receiver->value));
    }
}
