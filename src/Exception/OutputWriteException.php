<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

final class OutputWriteException extends InputOutputException
{
    public static function cannotWrite(string $path, ?string $reason = null): self
    {
        return new self(sprintf('Cannot write "%s"%s', $path, $reason === null ? '.' : ': ' . $reason));
    }
}
