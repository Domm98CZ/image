<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Format;

use Domm98CZ\Image\Driver\Imagick\ImagickCall;
use Domm98CZ\Image\Driver\Imagick\ImagickColor;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use Imagick;

final readonly class ImagickJpegFormat implements ImagickFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Jpeg;
    }

    public function isSupported(): bool
    {
        return ImagickCodec::isSupported(FormatName::Jpeg);
    }

    public function encode(Imagick $image, OutputFormatInterface $output): string
    {
        $options = OutputOptions::expect(JpegOutput::class, $output);
        $flattened = ImagickCall::run('flatten for JPEG', static function () use ($image, $options): Imagick {
            $canvas = new Imagick();
            $canvas->newImage($image->getImageWidth(), $image->getImageHeight(), ImagickColor::toPixel($options->background->withAlpha(255)));
            $canvas->compositeImage($image, Imagick::COMPOSITE_OVER, 0, 0);
            $canvas->setImageCompressionQuality($options->quality);
            $canvas->setInterlaceScheme($options->progressive ? Imagick::INTERLACE_PLANE : Imagick::INTERLACE_NO);

            return $canvas;
        });

        return ImagickCodec::blob($flattened, FormatName::Jpeg);
    }
}
