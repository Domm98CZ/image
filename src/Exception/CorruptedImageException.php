<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Format\FormatName;

final class CorruptedImageException extends InvalidInputException
{
    public static function truncated(int $offset, int $length, int $available): self
    {
        return new self(sprintf('Input is truncated: needed %d byte(s) at offset %d, only %d byte(s) available.', $length, $offset, $available));
    }

    public static function invalid(FormatName $format, string $reason): self
    {
        return new self(sprintf('Invalid %s data: %s.', $format->label(), $reason));
    }
}
