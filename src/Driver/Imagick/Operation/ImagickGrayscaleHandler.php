<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Grayscale;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Grayscale> */
final readonly class ImagickGrayscaleHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Grayscale::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        // Rec. 601 luma on every color row; the alpha row passes through.
        $image->colorMatrixImage([
            0.299, 0.587, 0.114, 0, 0,
            0.299, 0.587, 0.114, 0, 0,
            0.299, 0.587, 0.114, 0, 0,
            0, 0, 0, 1, 0,
            0, 0, 0, 0, 1,
        ]);

        return $image;
    }
}
