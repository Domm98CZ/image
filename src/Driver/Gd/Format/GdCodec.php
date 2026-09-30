<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Format;

use Closure;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\Gd\CapturedCall;
use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Exception\DriverOperationFailedException;
use Domm98CZ\Image\Format\FormatName;
use GdImage;

/** @internal Decode/encode plumbing shared by the per-format GD classes. */
final class GdCodec
{
    public static function decode(FormatName $format, string $bytes): GdImage
    {
        $call = CapturedCall::run(static fn(): GdImage|false => imagecreatefromstring($bytes));
        if (!$call->result instanceof GdImage) {
            throw CorruptedImageException::invalid($format, $call->error ?? 'GD could not decode the data');
        }

        return $call->result;
    }

    /** @param Closure(resource): bool $write */
    public static function encode(FormatName $format, Closure $write): string
    {
        $stream = fopen('php://memory', 'w+b');
        if ($stream === false) {
            throw DriverOperationFailedException::because(DriverName::Gd, 'open an in-memory buffer');
        }
        try {
            $call = CapturedCall::run(static fn(): bool => $write($stream));
            if ($call->result !== true) {
                throw DriverOperationFailedException::because(DriverName::Gd, 'encode ' . $format->label(), $call->error);
            }
            rewind($stream);

            return (string) stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    public static function supports(int $imageType): bool
    {
        return (imagetypes() & $imageType) !== 0;
    }
}
