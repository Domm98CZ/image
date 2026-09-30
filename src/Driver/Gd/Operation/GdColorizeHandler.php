<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Driver\Gd\GdFilter;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Colorize;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use GdImage;

/** @implements GdOperationHandlerInterface<Colorize> */
final readonly class GdColorizeHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Colorize::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        $strength = $operation->strength;
        $tint = $operation->tint;
        // value * (1 - s) + tint * s using two alpha-preserving filters: contrast scales around 127.5, colorize shifts.
        GdFilter::apply($image, IMG_FILTER_CONTRAST, (int) round(100 - 100 * sqrt(1 - $strength)));
        GdFilter::apply(
            $image,
            IMG_FILTER_COLORIZE,
            (int) round(($tint->red - 127.5) * $strength),
            (int) round(($tint->green - 127.5) * $strength),
            (int) round(($tint->blue - 127.5) * $strength),
        );

        return $image;
    }
}
