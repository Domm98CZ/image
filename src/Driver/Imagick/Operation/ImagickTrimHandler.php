<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Operation\Trim;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Trim> */
final readonly class ImagickTrimHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Trim::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        $image->setImageBackgroundColor($image->getImagePixelColor(0, 0));
        $image->trimImage(0.0);
        $image->setImagePage(0, 0, 0, 0);

        return $image;
    }
}
