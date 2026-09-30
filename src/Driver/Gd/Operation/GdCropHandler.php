<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Driver\Gd\GdCanvas;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Crop;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use GdImage;

/** @implements GdOperationHandlerInterface<Crop> */
final readonly class GdCropHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Crop::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        $area = $operation->area;
        $cropped = GdCanvas::blank($area->dimensions, Color::transparent());
        imagecopy($cropped, $image, 0, 0, $area->left(), $area->top(), $area->dimensions->width, $area->dimensions->height);

        return $cropped;
    }
}
