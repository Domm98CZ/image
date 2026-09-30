<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Output;

use Domm98CZ\Image\Format\FormatName;

final readonly class AvifOutput implements OutputFormatInterface
{
    public function __construct(
        public int $quality = 60,
        // 0 is slowest/smallest, 10 fastest/largest; libavif's own scale.
        public int $speed = 6,
    ) {
        OptionRange::assert('quality', $quality, 0, 100);
        OptionRange::assert('speed', $speed, 0, 10);
    }

    public function format(): FormatName
    {
        return FormatName::Avif;
    }
}
