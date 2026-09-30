<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation\Gif;

use Closure;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;

/** @internal The GIF logical screen while frames are composited; pixels are materialized only when needed. */
final readonly class GifCanvas
{
    private const TRANSPARENT_PIXEL = "\0\0\0\0";

    /** @param ?Closure(): PixelBuffer $pending */
    public function __construct(
        private Dimensions $screen,
        private ?string $bytes = null,
        private ?Closure $pending = null,
    ) {}

    /** @param Closure(): PixelBuffer $pixels */
    public function replacedBy(Closure $pixels): self
    {
        return new self($this->screen, null, $pixels);
    }

    public function pixels(): PixelBuffer
    {
        if ($this->pending !== null) {
            return ($this->pending)();
        }

        return new PixelBuffer($this->screen, $this->bytes ?? str_repeat(self::TRANSPARENT_PIXEL, $this->screen->pixelCount()));
    }

    // Frame pixels are drawn over the canvas; GIF transparency is binary, so "over" means "copy if not transparent".
    public function withFrame(PixelBuffer $frame, Point $origin): self
    {
        $canvas = $this->pixels()->bytes;
        $visible = (new Rectangle($origin, $frame->dimensions))->intersection(Rectangle::covering($this->screen));
        if ($visible === null) {
            return new self($this->screen, $canvas);
        }
        $screenStride = $this->screen->width * 4;
        $frameStride = $frame->dimensions->width * 4;
        for ($y = $visible->top(); $y < $visible->bottomExclusive(); ++$y) {
            $frameRow = ($y - $origin->y) * $frameStride;
            for ($x = $visible->left(); $x < $visible->rightExclusive(); ++$x) {
                $source = $frameRow + ($x - $origin->x) * 4;
                if ($frame->bytes[$source + 3] === "\0") {
                    continue;
                }
                $target = $y * $screenStride + $x * 4;
                $canvas[$target] = $frame->bytes[$source];
                $canvas[$target + 1] = $frame->bytes[$source + 1];
                $canvas[$target + 2] = $frame->bytes[$source + 2];
                $canvas[$target + 3] = $frame->bytes[$source + 3];
            }
        }

        return new self($this->screen, $canvas);
    }

    public function clearedArea(Rectangle $area): self
    {
        $visible = $area->intersection(Rectangle::covering($this->screen));
        $canvas = $this->pixels()->bytes;
        if ($visible === null) {
            return new self($this->screen, $canvas);
        }
        $stride = $this->screen->width * 4;
        $clearRow = str_repeat(self::TRANSPARENT_PIXEL, $visible->dimensions->width);
        for ($y = $visible->top(); $y < $visible->bottomExclusive(); ++$y) {
            $canvas = substr_replace($canvas, $clearRow, $y * $stride + $visible->left() * 4, strlen($clearRow));
        }

        return new self($this->screen, $canvas);
    }
}
