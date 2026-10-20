<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Format;

use Domm98CZ\Image\Driver\Gd\GdCanvas;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use GdImage;

final readonly class GdJpegFormat implements GdFormatInterface
{
    private const COMMENT = 0xFE;
    private const START_OF_SCAN = 0xDA;

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

        return self::withoutComments(GdCodec::encode(FormatName::Jpeg, static fn($stream): bool => imagejpeg($flattened, $stream, $options->quality)));
    }

    // libgd writes a "CREATOR: gd-jpeg ..." comment naming the library and its version into every file.
    private static function withoutComments(string $jpeg): string
    {
        $input = new BinaryString($jpeg);
        $kept = $input->slice(0, 2);
        $offset = 2;
        while ($input->has($offset, 4) && $input->uint8($offset) === 0xFF && $input->uint8($offset + 1) !== self::START_OF_SCAN) {
            $length = 2 + $input->uint16BigEndian($offset + 2);
            if ($input->uint8($offset + 1) !== self::COMMENT) {
                $kept .= $input->slice($offset, $length);
            }
            $offset += $length;
        }

        return $kept . substr($jpeg, $offset);
    }
}
