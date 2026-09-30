<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Gamma;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use GdImage;

/** @implements GdOperationHandlerInterface<Gamma> */
final readonly class GdGammaHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Gamma::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        imagegammacorrect($image, 1.0, $operation->gamma);

        return $image;
    }
}
