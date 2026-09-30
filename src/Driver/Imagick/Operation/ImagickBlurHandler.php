<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Blur;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Blur> */
final readonly class ImagickBlurHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Blur::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        $image->gaussianBlurImage(0, $operation->sigma);

        return $image;
    }
}
