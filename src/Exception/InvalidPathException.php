<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Format\FormatName;

final class InvalidPathException extends InvalidArgumentException
{
    public static function streamWrapper(string $path): self
    {
        return new self(sprintf('Only local filesystem paths are accepted, got stream wrapper path "%s".', $path));
    }

    public static function unknownExtension(string $path): self
    {
        return new self(sprintf('Cannot infer an output format from "%s"; pass an output format explicitly.', $path));
    }

    public static function extensionMismatch(string $path, FormatName $format): self
    {
        return new self(sprintf('"%s" has an extension for another format than the requested %s; use ".%s" or pick the matching output.', $path, $format->label(), $format->fileExtension()));
    }

    public static function nullByte(): self
    {
        return new self('Path contains a null byte.');
    }
}
