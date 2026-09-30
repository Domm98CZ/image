<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Format;

use Domm98CZ\Image\Driver\Imagick\ImagickCall;
use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\FormatName;
use Imagick;
use ImagickException;

/** @internal */
final class ImagickCodec
{
    public static function coder(FormatName $format): string
    {
        return match ($format) {
            FormatName::Jpeg => 'JPEG',
            FormatName::Png => 'PNG',
            FormatName::Gif => 'GIF',
            FormatName::Webp => 'WEBP',
            FormatName::Avif => 'AVIF',
            FormatName::Heic => 'HEIC',
        };
    }

    /** @return list<string> */
    public static function decodedAs(FormatName $format): array
    {
        // ImageMagick 6 (e.g. Debian bookworm) reads AVIF through its HEIC coder and reports it as such.
        return $format === FormatName::Avif ? ['AVIF', 'HEIC'] : [self::coder($format)];
    }

    public static function isSupported(FormatName $format): bool
    {
        return Imagick::queryFormats(self::coder($format)) !== [];
    }

    public static function decode(FormatName $format, string $bytes): Imagick
    {
        $image = new Imagick();
        try {
            $image->readImageBlob($bytes);
        } catch (ImagickException $exception) {
            throw CorruptedImageException::invalid($format, $exception->getMessage());
        }
        // ImageMagick sniffs the coder itself and a filename prefix does not pin it, so verify the result.
        $decodedAs = strtoupper($image->getImageFormat());
        if (!in_array($decodedAs, self::decodedAs($format), true)) {
            throw CorruptedImageException::invalid($format, sprintf('ImageMagick decoded the data as %s', $decodedAs));
        }

        return $image;
    }

    public static function blob(Imagick $image, FormatName $format): string
    {
        return ImagickCall::run('encode ' . $format->label(), static function () use ($image, $format): string {
            $image->setImageFormat(self::coder($format));

            return $image->getImageBlob();
        });
    }
}
