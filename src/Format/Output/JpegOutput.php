<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Output;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Format\FormatName;

final readonly class JpegOutput implements OutputFormatInterface
{
    public function __construct(
        public int $quality = 85,
        public bool $progressive = false,
        // JPEG has no alpha: transparent pixels are composited onto this color.
        public Color $background = new Color(255, 255, 255),
    ) {
        OptionRange::assert('quality', $quality, 1, 100);
    }

    public function format(): FormatName
    {
        return FormatName::Jpeg;
    }
}
