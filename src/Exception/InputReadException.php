<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Throwable;

final class InputReadException extends InputOutputException
{
    public static function fileNotReadable(string $path, ?string $reason = null): self
    {
        return new self(sprintf('File "%s" is not a readable regular file%s', $path, $reason === null ? '.' : ': ' . $reason));
    }

    public static function streamNotReadable(): self
    {
        return new self('The given stream is not readable.');
    }

    public static function streamFailed(Throwable $previous): self
    {
        return new self('Reading the input stream failed: ' . $previous->getMessage(), 0, $previous);
    }
}
