<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Format;

use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use GdImage;

// GD has no HEIC/HEIF codec at all, on any libgd build; decode/encode are unreachable in practice
// because isSupported() always gates them out first (GdDriver::decodingSupport()/encodingSupport()).
final readonly class GdHeicFormat implements GdFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Heic;
    }

    public function isSupported(): bool
    {
        return false;
    }

    public function decode(string $bytes): GdImage
    {
        throw UnsupportedFormatException::cannotDecode(FormatName::Heic, DriverName::Gd);
    }

    public function encode(GdImage $image, OutputFormatInterface $output): string
    {
        throw UnsupportedFormatException::cannotEncode(FormatName::Heic, DriverName::Gd);
    }
}
