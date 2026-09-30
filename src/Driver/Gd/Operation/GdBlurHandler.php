<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Driver\Gd\GdGaussianBlur;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Blur;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use GdImage;

/** @implements GdOperationHandlerInterface<Blur> */
final readonly class GdBlurHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Blur::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        return GdGaussianBlur::apply($image, $operation->sigma);
    }
}
