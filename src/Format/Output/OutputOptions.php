<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Output;

use Domm98CZ\Image\Exception\InvalidEncodingOptionException;

final class OutputOptions
{
    /**
     * @template T of OutputFormatInterface
     * @param class-string<T> $expected
     * @return T
     */
    public static function expect(string $expected, OutputFormatInterface $output): OutputFormatInterface
    {
        if (!$output instanceof $expected) {
            throw InvalidEncodingOptionException::unexpectedOutput($expected, $output);
        }

        return $output;
    }
}
