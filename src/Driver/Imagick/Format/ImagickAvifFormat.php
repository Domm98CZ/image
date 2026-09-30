<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Format;

use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use Imagick;

final readonly class ImagickAvifFormat implements ImagickFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Avif;
    }

    public function isSupported(): bool
    {
        return ImagickCodec::isSupported(FormatName::Avif);
    }

    public function encode(Imagick $image, OutputFormatInterface $output): string
    {
        $options = OutputOptions::expect(AvifOutput::class, $output);
        $image->setImageCompressionQuality($options->quality);
        $image->setOption('heic:speed', (string) $options->speed);

        return ImagickCodec::blob($image, FormatName::Avif);
    }
}
