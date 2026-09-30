<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Format;

use Domm98CZ\Image\Driver\Imagick\ImagickColor;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use Domm98CZ\Image\Format\Output\PngOutput;
use Imagick;

final readonly class ImagickPngFormat implements ImagickFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Png;
    }

    public function isSupported(): bool
    {
        return ImagickCodec::isSupported(FormatName::Png);
    }

    public function encode(Imagick $image, OutputFormatInterface $output): string
    {
        $options = OutputOptions::expect(PngOutput::class, $output);
        $image->setOption('png:compression-level', (string) $options->compressionLevel);
        if (!$options->keepAlphaChannel && self::isFullyOpaque($image)) {
            // Without the alpha trait ImageMagick picks the smallest opaque colour type (gray, palette or RGB) itself.
            $image->setImageAlphaChannel(self::alphaChannelOff());
        }

        return ImagickCodec::blob($image, FormatName::Png);
    }

    private static function isFullyOpaque(Imagick $image): bool
    {
        if (!$image->getImageAlphaChannel()) {
            return true;
        }
        // A zero-width range plus one sampled pixel works whether the channel stores alpha (IM7) or opacity (IM6).
        $range = $image->getImageChannelRange(Imagick::CHANNEL_ALPHA);

        return $range['minima'] === $range['maxima'] && ImagickColor::fromPixel($image->getImagePixelColor(0, 0))->alpha === 255;
    }

    /**
     * ImageMagick 7 only clears the trait with OffAlphaChannel; ImageMagick 6 knows DeactivateAlphaChannel for the same thing.
     *
     * @return Imagick::ALPHACHANNEL_*
     */
    private static function alphaChannelOff(): int
    {
        if (!defined(Imagick::class . '::ALPHACHANNEL_OFF')) {
            return Imagick::ALPHACHANNEL_DEACTIVATE;
        }
        /** @var Imagick::ALPHACHANNEL_* $off */
        $off = constant(Imagick::class . '::ALPHACHANNEL_OFF');

        return $off;
    }
}
