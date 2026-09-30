<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Crop;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Crop> */
final readonly class ImagickCropHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Crop::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        $area = $operation->area;
        $image->cropImage($area->dimensions->width, $area->dimensions->height, $area->left(), $area->top());
        // Crop keeps the old virtual canvas offset, which later operations and encoders would honour.
        $image->setImagePage(0, 0, 0, 0);

        return $image;
    }
}
