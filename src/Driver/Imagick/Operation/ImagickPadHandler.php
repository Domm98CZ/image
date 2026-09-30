<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Imagick\ImagickColor;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Operation\Pad;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Pad> */
final readonly class ImagickPadHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Pad::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        $dimensions = $operation->resultingDimensions(new Dimensions($image->getImageWidth(), $image->getImageHeight()));
        $canvas = new Imagick();
        $canvas->newImage($dimensions->width, $dimensions->height, ImagickColor::toPixel($operation->background));
        $canvas->compositeImage($image, Imagick::COMPOSITE_COPY, $operation->left, $operation->top);
        $canvas->setImagePage(0, 0, 0, 0);

        return $canvas;
    }
}
