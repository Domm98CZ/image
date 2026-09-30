<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Security;

use Domm98CZ\Image\Exception\InvalidPathException;

final class LocalPath
{
    public static function assertValid(string $path): void
    {
        if (str_contains($path, "\0")) {
            throw InvalidPathException::nullByte();
        }
        // Wrappers (phar://, http://, data://...) would turn a path into remote or code-loading I/O.
        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $path) === 1) {
            throw InvalidPathException::streamWrapper($path);
        }
    }
}
