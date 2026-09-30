<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Format;

use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Imagick;

final readonly class ImagickWebpFormat implements ImagickFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Webp;
    }

    public function isSupported(): bool
    {
        return ImagickCodec::isSupported(FormatName::Webp);
    }

    public function encode(Imagick $image, OutputFormatInterface $output): string
    {
        $options = OutputOptions::expect(WebpOutput::class, $output);
        $image->setImageCompressionQuality($options->quality);
        $image->setOption('webp:lossless', $options->lossless ? 'true' : 'false');
        // Without "exact", ImageMagick 7 lets libwebp alter RGB values even in lossless mode (breaks LSB watermarks).
        $image->setOption('webp:exact', $options->lossless ? 'true' : 'false');

        return ImagickCodec::blob($image, FormatName::Webp);
    }
}
