<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Header;

use Domm98CZ\Image\Format\Binary\BinaryString;

interface HeaderProbeInterface
{
    public function probe(BinaryString $input): ImageHeader;
}
