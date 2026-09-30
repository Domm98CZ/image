<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Output;

use Domm98CZ\Image\Exception\InvalidPathException;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Format\FormatName;

final class OutputFormats
{
    public static function defaultFor(FormatName $format): OutputFormatInterface
    {
        return match ($format) {
            FormatName::Jpeg => new JpegOutput(),
            FormatName::Png => new PngOutput(),
            FormatName::Gif => new GifOutput(),
            FormatName::Webp => new WebpOutput(),
            FormatName::Avif => new AvifOutput(),
            // Decode-only: no encoder exists for HEIC (patent/licensing reasons - output goes to WebP/AVIF instead).
            FormatName::Heic => throw UnsupportedFormatException::noDriverCanEncode(FormatName::Heic),
        };
    }

    // A file named .png holding JPEG bytes gets served and cached with the wrong type, so a mismatch is an error.
    public static function forPath(string $path, ?OutputFormatInterface $output): OutputFormatInterface
    {
        $fromExtension = FormatName::tryFromFileExtension(pathinfo($path, PATHINFO_EXTENSION));
        if ($output === null) {
            return self::defaultFor($fromExtension ?? throw InvalidPathException::unknownExtension($path));
        }
        if ($fromExtension !== null && $fromExtension !== $output->format()) {
            throw InvalidPathException::extensionMismatch($path, $output->format());
        }

        return $output;
    }
}
