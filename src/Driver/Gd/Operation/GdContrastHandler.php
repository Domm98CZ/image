<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Driver\Gd\GdFilter;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Contrast;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use GdImage;

/** @implements GdOperationHandlerInterface<Contrast> */
final readonly class GdContrastHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Contrast::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        // GD scales by ((100 - arg) / 100)^2, i.e. our factor with the sign of the level flipped.
        GdFilter::apply($image, IMG_FILTER_CONTRAST, -$operation->level);

        return $image;
    }
}
