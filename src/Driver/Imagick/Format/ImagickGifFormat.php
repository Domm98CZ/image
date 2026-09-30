<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Format;

use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\GifOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use Imagick;

final readonly class ImagickGifFormat implements ImagickFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Gif;
    }

    public function isSupported(): bool
    {
        return ImagickCodec::isSupported(FormatName::Gif);
    }

    public function encode(Imagick $image, OutputFormatInterface $output): string
    {
        OutputOptions::expect(GifOutput::class, $output);

        return ImagickCodec::blob($image, FormatName::Gif);
    }
}
