<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Output;

use Domm98CZ\Image\Exception\OutputWriteException;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Security\LocalPath;

final readonly class FileWriter
{
    public function write(string $path, EncodedImage $image): void
    {
        LocalPath::assertValid($path);
        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw OutputWriteException::cannotWrite($path, sprintf('directory "%s" is not writable', $directory));
        }

        if (is_dir($path)) {
            throw OutputWriteException::cannotWrite($path, 'the path is a directory');
        }
        // Replacing a file keeps its permissions, as an in-place write would; a new file gets the umask default.
        $permissions = is_file($path) ? fileperms($path) : false;

        // Write-then-rename so readers (e.g. a CDN serving the file) never see a half-written image.
        $temporary = sprintf('%s.%s.tmp', $path, bin2hex(random_bytes(6)));
        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error ??= $message;

            return true;
        });
        try {
            $written = file_put_contents($temporary, $image->bytes, LOCK_EX) === strlen($image->bytes)
                && ($permissions === false || chmod($temporary, $permissions & 0o777))
                && rename($temporary, $path);
        } finally {
            restore_error_handler();
        }
        if (!$written) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw OutputWriteException::cannotWrite($path, $error);
        }
    }
}
