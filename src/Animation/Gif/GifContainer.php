<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation\Gif;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Geometry\Dimensions;

/** @internal */
final readonly class GifContainer
{
    /** @param non-empty-list<GifFrame> $frames */
    public function __construct(
        public Dimensions $screen,
        public ?string $globalColorTable,
        // NETSCAPE2.0 loop count as stored: 0 = forever; null = no loop extension (play once).
        public ?int $loopCount,
        public array $frames,
    ) {}

    // A single-image GIF that any decoder (GD included) can read on its own.
    public function standaloneFrame(int $index): string
    {
        $frame = $this->frames[$index] ?? throw CorruptedImageException::invalid(FormatName::Gif, sprintf('no frame %d', $index));
        $colorTable = $frame->localColorTable ?? $this->globalColorTable
            ?? throw CorruptedImageException::invalid(FormatName::Gif, sprintf('frame %d has no color table', $index));
        $dimensions = $frame->dimensions();

        return GifBytes::header($dimensions, $colorTable)
            . ($frame->transparentIndex === null ? '' : GifBytes::graphicControl(GifDisposal::Unspecified, 0, $frame->transparentIndex))
            . GifBytes::imageDescriptor($dimensions, null, $frame->interlaced)
            . chr($frame->lzwMinimumCodeSize) . $frame->imageData
            . GifBytes::TRAILER;
    }
}
