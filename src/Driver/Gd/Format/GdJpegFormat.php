<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Format;

use Domm98CZ\Image\Driver\Gd\GdCanvas;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use GdImage;

final readonly class GdJpegFormat implements GdFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Jpeg;
    }

    public function isSupported(): bool
    {
        return GdCodec::supports(IMG_JPEG);
    }

    public function decode(string $bytes): GdImage
    {
        return GdCodec::decode(FormatName::Jpeg, $bytes);
    }

    public function encode(GdImage $image, OutputFormatInterface $output): string
    {
        $options = OutputOptions::expect(JpegOutput::class, $output);
        // Flattening onto a fresh canvas also keeps the interlace flag change off the caller's handle.
        $flattened = GdCanvas::blank(GdCanvas::dimensions($image), $options->background);
        imagealphablending($flattened, true);
        imagecopy($flattened, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imageinterlace($flattened, $options->progressive);

        return GdCodec::encode(FormatName::Jpeg, static fn($stream): bool => imagejpeg($flattened, $stream, $options->quality));
    }
}
