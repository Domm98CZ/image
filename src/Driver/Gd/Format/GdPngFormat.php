<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Format;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Driver\Gd\GdCanvas;
use Domm98CZ\Image\Driver\Gd\GdOpacity;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use Domm98CZ\Image\Format\Output\PngOutput;
use GdImage;

final readonly class GdPngFormat implements GdFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Png;
    }

    public function isSupported(): bool
    {
        return GdCodec::supports(IMG_PNG);
    }

    public function decode(string $bytes): GdImage
    {
        return GdCodec::decode(FormatName::Png, $bytes);
    }

    public function encode(GdImage $image, OutputFormatInterface $output): string
    {
        $options = OutputOptions::expect(PngOutput::class, $output);
        $source = $options->keepAlphaChannel || !GdOpacity::isFullyOpaque($image) ? $image : self::withoutAlphaChannel($image);

        return GdCodec::encode(FormatName::Png, static fn($stream): bool => imagepng($source, $stream, $options->compressionLevel));
    }

    // A fresh canvas keeps the save-alpha flag change (and any transparent colour key libgd would turn into tRNS) off the caller's handle.
    private static function withoutAlphaChannel(GdImage $image): GdImage
    {
        $copy = GdCanvas::blank(GdCanvas::dimensions($image), Color::transparent());
        imagecopy($copy, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imageinterlace($copy, imageinterlace($image));
        imagesavealpha($copy, false);

        return $copy;
    }
}
