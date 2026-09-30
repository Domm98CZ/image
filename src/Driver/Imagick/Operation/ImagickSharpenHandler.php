<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Operation\Sharpen;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Sharpen> */
final readonly class ImagickSharpenHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Sharpen::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        // Sigma ~0.707 matches the 3x3 Gaussian the GD driver uses for the same unsharp mask.
        $image->unsharpMaskImage(0, M_SQRT1_2, $operation->amount, 0);

        return $image;
    }
}
