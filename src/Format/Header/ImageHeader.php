<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Header;

use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Geometry\Dimensions;

final readonly class ImageHeader
{
    public Dimensions $largestFrame;

    public function __construct(
        public FormatName $format,
        public Dimensions $canvas,
        public int $frameCount = 1,
        public bool $hasAlpha = false,
        ?Dimensions $largestFrame = null,
    ) {
        $this->largestFrame = $largestFrame ?? $canvas;
    }

    public function isAnimated(): bool
    {
        return $this->frameCount > 1;
    }
}
