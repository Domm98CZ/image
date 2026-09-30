<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Color;

interface PixelFilterInterface
{
    public function apply(PixelBuffer $pixels): PixelBuffer;
}
