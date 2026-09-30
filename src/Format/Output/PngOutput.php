<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Output;

use Domm98CZ\Image\Format\FormatName;

final readonly class PngOutput implements OutputFormatInterface
{
    // A fully opaque image is written without an alpha channel (smaller file, same pixels) unless keepAlphaChannel asks for one.
    public function __construct(
        public int $compressionLevel = 6,
        public bool $keepAlphaChannel = false,
    ) {
        OptionRange::assert('compressionLevel', $compressionLevel, 0, 9);
    }

    public function format(): FormatName
    {
        return FormatName::Png;
    }
}
