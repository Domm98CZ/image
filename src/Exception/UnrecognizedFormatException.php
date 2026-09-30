<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

final class UnrecognizedFormatException extends InvalidInputException
{
    public static function emptyInput(): self
    {
        return new self('Input is empty.');
    }

    public static function unknownSignature(): self
    {
        return new self('Input is not a JPEG, PNG, GIF, WebP or AVIF image (unrecognized file signature).');
    }
}
