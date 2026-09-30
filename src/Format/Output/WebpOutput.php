<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Output;

use Domm98CZ\Image\Format\FormatName;

final readonly class WebpOutput implements OutputFormatInterface
{
    public function __construct(
        public int $quality = 80,
        public bool $lossless = false,
    ) {
        OptionRange::assert('quality', $quality, 1, 100);
    }

    public function format(): FormatName
    {
        return FormatName::Webp;
    }
}
