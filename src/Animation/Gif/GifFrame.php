<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation\Gif;

use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;

/** @internal One image block of a GIF, with its compressed data kept verbatim. */
final readonly class GifFrame
{
    public function __construct(
        public Rectangle $area,
        public bool $interlaced,
        public ?string $localColorTable,
        public ?int $transparentIndex,
        public GifDisposal $disposal,
        public int $delayCentiseconds,
        public int $lzwMinimumCodeSize,
        // Data sub-blocks exactly as stored, including the zero-length terminator.
        public string $imageData,
    ) {}

    public function origin(): Point
    {
        return $this->area->origin;
    }

    public function dimensions(): Dimensions
    {
        return $this->area->dimensions;
    }
}
