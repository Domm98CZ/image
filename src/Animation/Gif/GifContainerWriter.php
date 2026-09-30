<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation\Gif;

use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Geometry\Dimensions;

// Splices single-frame GIFs (each with its own palette) into one animation of full-canvas frames.
final readonly class GifContainerWriter
{
    public function __construct(
        private GifContainerReader $reader = new GifContainerReader(),
    ) {}

    /** @param list<GifFrameSource> $frames */
    public function write(Dimensions $canvas, ?int $loopCount, array $frames): string
    {
        $bytes = GifBytes::header($canvas, null) . ($loopCount === null ? '' : GifBytes::loopExtension($loopCount));
        foreach ($frames as $source) {
            $single = $this->reader->read(new BinaryString($source->gif));
            $frame = $single->frames[0];
            // Every frame repaints the full canvas, so clearing to transparent afterwards is always correct.
            $bytes .= GifBytes::graphicControl(GifDisposal::RestoreBackground, $source->delayCentiseconds, $frame->transparentIndex)
                . GifBytes::imageDescriptor($frame->dimensions(), $frame->localColorTable ?? $single->globalColorTable, $frame->interlaced)
                . chr($frame->lzwMinimumCodeSize) . $frame->imageData;
        }

        return $bytes . GifBytes::TRAILER;
    }
}
