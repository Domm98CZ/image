<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Format;

use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use GdImage;

final readonly class GdAvifFormat implements GdFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Avif;
    }

    public function isSupported(): bool
    {
        return GdCodec::supports(IMG_AVIF);
    }

    public function decode(string $bytes): GdImage
    {
        return GdCodec::decode(FormatName::Avif, $bytes);
    }

    public function encode(GdImage $image, OutputFormatInterface $output): string
    {
        $options = OutputOptions::expect(AvifOutput::class, $output);

        return GdCodec::encode(FormatName::Avif, static fn($stream): bool => imageavif($image, $stream, $options->quality, $options->speed));
    }
}
