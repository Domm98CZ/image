<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark\Steganography;

use Domm98CZ\Image\Operation\PixelOperationInterface;
use Domm98CZ\Image\Watermark\PixelWatermarkInterface;

// Always scheduled after every other pixel step: any later change to the pixels would damage it.
interface SteganographicWatermarkInterface extends PixelWatermarkInterface, PixelOperationInterface
{
    public function survivesLossyEncoding(): bool;
}
