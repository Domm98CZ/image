<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Format\Output\OutputFormatInterface;

final class InvalidEncodingOptionException extends InvalidArgumentException
{
    /** @param class-string<OutputFormatInterface> $expected */
    public static function unexpectedOutput(string $expected, OutputFormatInterface $given): self
    {
        return new self(sprintf('Expected %s, got %s.', $expected, $given::class));
    }

    public static function outOfRange(string $option, int $value, int $min, int $max): self
    {
        return new self(sprintf('Encoding option "%s" must be within %d-%d, got %d.', $option, $min, $max, $value));
    }
}
