<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation\Gif;

use Domm98CZ\Image\Animation\AnimatedImage;
use Domm98CZ\Image\Animation\AnimationCodecInterface;
use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\ImageHandleInterface;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\GifOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Security\InputGuard;
use Domm98CZ\Image\Security\MemoryBudget;
use Domm98CZ\Image\Security\ValidatedInput;

// Pure-PHP GIF container handling: any driver that can decode/encode a single GIF frame animates GIFs.
final readonly class GifAnimationCodec implements AnimationCodecInterface
{
    public function __construct(
        private DriverInterface $driver,
        private InputGuard $guard,
        private GifContainerReader $reader = new GifContainerReader(),
        private GifContainerWriter $writer = new GifContainerWriter(),
    ) {}

    public function format(): FormatName
    {
        return FormatName::Gif;
    }

    public function canDecode(): bool
    {
        return $this->driver->decodingSupport(FormatName::Gif)->isSupported();
    }

    public function canEncode(): bool
    {
        return true;
    }

    public function decode(ValidatedInput $input): AnimatedImage
    {
        $container = $this->reader->read(new BinaryString($input->bytes));
        $screen = $container->screen;
        $canvas = new GifCanvas($screen);
        $frames = [];

        foreach ($container->frames as $index => $gifFrame) {
            $decoded = $this->decodeFrame($container->standaloneFrame($index));
            $coversScreen = $gifFrame->area->equals(Rectangle::covering($screen));

            if ($coversScreen && $gifFrame->transparentIndex === null && $gifFrame->disposal !== GifDisposal::RestorePrevious) {
                // Fast path: an opaque full-screen frame is its own composite; no pixel copying needed.
                $snapshot = $decoded;
                $canvas = $canvas->replacedBy(fn(): PixelBuffer => $this->driver->readPixels($decoded, Rectangle::covering($screen)));
            } else {
                $previous = $canvas;
                $canvas = $canvas->withFrame($this->driver->readPixels($decoded, Rectangle::covering($gifFrame->dimensions())), $gifFrame->origin());
                $snapshot = $this->driver->writePixels($this->driver->create($screen, Color::transparent()), $canvas->pixels(), Point::origin());
                if ($gifFrame->disposal === GifDisposal::RestorePrevious) {
                    $frames[] = $this->frame($snapshot, $gifFrame);
                    $canvas = $previous;
                    continue;
                }
            }
            $frames[] = $this->frame($snapshot, $gifFrame);
            if ($gifFrame->disposal === GifDisposal::RestoreBackground) {
                $canvas = $canvas->clearedArea($gifFrame->area);
            }
        }

        return new AnimatedImage($frames, self::playCount($container->loopCount), FormatName::Gif);
    }

    public function encode(AnimatedImage $animation, OutputFormatInterface $output): EncodedImage
    {
        $options = OutputOptions::expect(GifOutput::class, $output);
        $sources = array_map(
            static fn(Frame $frame): GifFrameSource => new GifFrameSource(
                $frame->image->encode($options)->bytes,
                (int) round($frame->delayMilliseconds / 10),
            ),
            $animation->frames,
        );

        return new EncodedImage($this->writer->write($animation->dimensions, self::loopCount($animation->playCount), $sources), FormatName::Gif);
    }

    // NETSCAPE2.0 stores repetitions after the first play (0 = forever); no extension means play once.
    private static function playCount(?int $loopCount): int
    {
        return match (true) {
            $loopCount === null => 1,
            $loopCount === 0 => 0,
            default => $loopCount + 1,
        };
    }

    private static function loopCount(int $playCount): ?int
    {
        return match (true) {
            $playCount === 0 => 0,
            $playCount === 1 => null,
            default => $playCount - 1,
        };
    }

    private function decodeFrame(string $standaloneGif): ImageHandleInterface
    {
        // Frame bytes come from validated input, but each frame's own dimensions are still checked against Limits.
        return $this->driver->decode($this->guard->inspect($standaloneGif, MemoryBudget::unlimited()));
    }

    private function frame(ImageHandleInterface $handle, GifFrame $gifFrame): Frame
    {
        return new Frame(new Image($handle, $this->driver, Orientation::TopLeft, FormatName::Gif), $gifFrame->delayCentiseconds * 10);
    }
}
