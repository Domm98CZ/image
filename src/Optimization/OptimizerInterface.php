<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Optimization;

use Domm98CZ\Image\Format\EncodedImage;

// Runs on the encoded file, after encoding and before metadata watermarks (so stripping never removes them).
interface OptimizerInterface
{
    public function optimize(EncodedImage $image): EncodedImage;
}
