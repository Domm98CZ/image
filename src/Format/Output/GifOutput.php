<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Output;

use Domm98CZ\Image\Format\FormatName;

final readonly class GifOutput implements OutputFormatInterface
{
    public function format(): FormatName
    {
        return FormatName::Gif;
    }
}
