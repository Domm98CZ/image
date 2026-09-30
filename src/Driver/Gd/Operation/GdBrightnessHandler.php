<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Driver\Gd\GdFilter;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Brightness;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use GdImage;

/** @implements GdOperationHandlerInterface<Brightness> */
final readonly class GdBrightnessHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Brightness::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        GdFilter::apply($image, IMG_FILTER_BRIGHTNESS, $operation->offset());

        return $image;
    }
}
