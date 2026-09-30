<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation;

use Domm98CZ\Image\Animation\Gif\GifAnimationCodec;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Driver\Imagick\ImagickDriver;
use Domm98CZ\Image\Driver\Imagick\ImagickWebpAnimationCodec;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Pipeline\Transfer;
use Domm98CZ\Image\Security\InputGuard;

final readonly class AnimationCodecs
{
    /** @param list<AnimationCodecInterface> $codecs */
    public function __construct(
        private array $codecs,
    ) {}

    public static function forDrivers(DriverRegistry $drivers, InputGuard $guard, Transfer $transfer): self
    {
        $codecs = [];
        $gifDecoders = array_filter($drivers->drivers, static fn(DriverInterface $driver): bool => $driver->decodingSupport(FormatName::Gif)->isSupported());
        if ($gifDecoders !== []) {
            $codecs[] = new GifAnimationCodec(array_values($gifDecoders)[0], $guard);
        }
        foreach ($drivers->drivers as $driver) {
            if ($driver instanceof ImagickDriver) {
                $codecs[] = new ImagickWebpAnimationCodec($driver, $transfer);
            }
        }

        return new self($codecs);
    }

    public function decoderFor(FormatName $format): AnimationCodecInterface
    {
        foreach ($this->codecs as $codec) {
            if ($codec->format() === $format && $codec->canDecode()) {
                return $codec;
            }
        }

        throw UnsupportedFormatException::noAnimationCodec($format);
    }

    public function encoderFor(OutputFormatInterface $output): AnimationCodecInterface
    {
        $format = $output->format();
        if ($format !== FormatName::Gif && $format !== FormatName::Webp) {
            throw UnsupportedFormatException::cannotEncodeAnimation($format);
        }
        foreach ($this->codecs as $codec) {
            if ($codec->format() === $format && $codec->canEncode()) {
                return $codec;
            }
        }

        throw UnsupportedFormatException::noAnimationCodec($format);
    }
}
