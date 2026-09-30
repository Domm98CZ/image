<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark;

use Domm98CZ\Image\Format\EncodedImage;

// Works on the encoded file (after the pixels are final), so it runs after encoding.
interface EncodedWatermarkInterface extends WatermarkInterface
{
    public function apply(EncodedImage $image): EncodedImage;
}
