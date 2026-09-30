<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick;

use Domm98CZ\Image\Animation\AnimatedImage;
use Domm98CZ\Image\Animation\AnimationCodecInterface;
use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\Driver\Imagick\Format\ImagickCodec;
use Domm98CZ\Image\Exception\IncompatibleHandleException;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Pipeline\Transfer;
use Domm98CZ\Image\Security\ValidatedInput;
use Imagick;
use ImagickPixel;

// Animated WebP needs libwebp's animation API, which only ImageMagick exposes to PHP.
final readonly class ImagickWebpAnimationCodec implements AnimationCodecInterface
{
    private const TICKS_PER_SECOND = 100;

    public function __construct(
        private ImagickDriver $driver,
        private Transfer $transfer,
    ) {}

    public function format(): FormatName
    {
        return FormatName::Webp;
    }

    public function canDecode(): bool
    {
        return $this->driver->animatedWebpDecoding()->isSupported();
    }

    public function canEncode(): bool
    {
        return $this->driver->animatedWebpEncoding()->isSupported();
    }

    public function decode(ValidatedInput $input): AnimatedImage
    {
        $source = ImagickCodec::decode(FormatName::Webp, $input->bytes);

        return ImagickCall::run('decode animated WebP', function () use ($source): AnimatedImage {
            // ImageMagick's "iterations" are plays (0 = forever), the same meaning as AnimatedImage::$playCount.
            $playCount = $source->getImageIterations();
            $coalesced = $source->coalesceImages();
            $frames = [];
            for ($index = 0; $index < $coalesced->getNumberImages(); ++$index) {
                $coalesced->setIteratorIndex($index);
                $single = $coalesced->getImage();
                $single->setImagePage(0, 0, 0, 0);
                ImagickColorProfile::toSrgb($single);
                $ticksPerSecond = $single->getImageTicksPerSecond() > 0 ? $single->getImageTicksPerSecond() : self::TICKS_PER_SECOND;
                $delay = (int) round($single->getImageDelay() * 1000 / $ticksPerSecond);
                $frames[] = new Frame(new Image(new ImagickImageHandle($single), $this->driver, Orientation::TopLeft, FormatName::Webp), $delay);
            }

            return new AnimatedImage($frames, $playCount, FormatName::Webp);
        });
    }

    public function encode(AnimatedImage $animation, OutputFormatInterface $output): EncodedImage
    {
        $options = OutputOptions::expect(WebpOutput::class, $output);
        $sequence = new Imagick();
        foreach ($animation->frames as $frame) {
            $handle = $frame->image->driverName() === $this->driver->name()
                ? $frame->image->handle()
                : $this->transfer->move($frame->image->handle(), $frame->image->driver(), $this->driver);
            if (!$handle instanceof ImagickImageHandle) {
                throw IncompatibleHandleException::belongsTo($handle->driver(), $this->driver->name());
            }
            $copy = clone $handle->image;
            ImagickCall::run('prepare an animation frame', static function () use ($copy, $frame, $options): void {
                $copy->stripImage();
                $copy->setImageFormat(ImagickCodec::coder(FormatName::Webp));
                $copy->setImageTicksPerSecond(self::TICKS_PER_SECOND);
                $copy->setImageDelay((int) round($frame->delayMilliseconds * self::TICKS_PER_SECOND / 1000));
                $copy->setImageBackgroundColor(new ImagickPixel('transparent'));
                $copy->setImageDispose(Imagick::DISPOSE_NONE);
                $copy->setImageCompressionQuality($options->quality);
            });
            $sequence->addImage($copy);
        }

        $bytes = ImagickCall::run('encode animated WebP', static function () use ($sequence, $animation, $options): string {
            $sequence->setIteratorIndex(0);
            $sequence->setImageIterations($animation->playCount);
            $sequence->setOption('webp:lossless', $options->lossless ? 'true' : 'false');
            $sequence->setOption('webp:exact', $options->lossless ? 'true' : 'false');

            return $sequence->getImagesBlob();
        });

        return new EncodedImage($bytes, FormatName::Webp);
    }
}
