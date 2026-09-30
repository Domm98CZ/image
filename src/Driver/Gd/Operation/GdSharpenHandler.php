<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Operation\Sharpen;
use GdImage;

/** @implements GdOperationHandlerInterface<Sharpen> */
final readonly class GdSharpenHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Sharpen::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        $a = $operation->amount;
        // (1 + a) * identity - a * Gaussian3x3; the 0.5 offset rounds instead of truncating.
        imageconvolution($image, [
            [-$a / 16, -2 * $a / 16, -$a / 16],
            [-2 * $a / 16, 1 + $a - 4 * $a / 16, -2 * $a / 16],
            [-$a / 16, -2 * $a / 16, -$a / 16],
        ], 1.0, 0.5);

        return $image;
    }
}
