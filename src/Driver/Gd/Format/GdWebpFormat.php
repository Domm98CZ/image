<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Format;

use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use Domm98CZ\Image\Format\Output\WebpOutput;
use GdImage;

final readonly class GdWebpFormat implements GdFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Webp;
    }

    public function isSupported(): bool
    {
        return GdCodec::supports(IMG_WEBP);
    }

    public function decode(string $bytes): GdImage
    {
        return GdCodec::decode(FormatName::Webp, $bytes);
    }

    public function encode(GdImage $image, OutputFormatInterface $output): string
    {
        $options = OutputOptions::expect(WebpOutput::class, $output);
        $quality = $options->lossless ? IMG_WEBP_LOSSLESS : $options->quality;

        return GdCodec::encode(FormatName::Webp, static fn($stream): bool => imagewebp($image, $stream, $quality));
    }
}
