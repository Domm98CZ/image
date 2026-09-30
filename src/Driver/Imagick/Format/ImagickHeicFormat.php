<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Format;

use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Imagick;

// Decode-only: no encoder is registered, so encode() is unreachable in practice (no Output type targets
// HEIC and ImagickDriver::encodingSupport() only ever calls formatFor() for the requested output format).
final readonly class ImagickHeicFormat implements ImagickFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Heic;
    }

    public function isSupported(): bool
    {
        return ImagickCodec::isSupported(FormatName::Heic);
    }

    public function encode(Imagick $image, OutputFormatInterface $output): string
    {
        throw UnsupportedFormatException::cannotEncode(FormatName::Heic, DriverName::Imagick);
    }
}
