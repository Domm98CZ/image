<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Flip;
use Domm98CZ\Image\Operation\FlipDirection;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use GdImage;

/** @implements GdOperationHandlerInterface<Flip> */
final readonly class GdFlipHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Flip::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        imageflip($image, match ($operation->direction) {
            FlipDirection::Horizontal => IMG_FLIP_HORIZONTAL,
            FlipDirection::Vertical => IMG_FLIP_VERTICAL,
            FlipDirection::Both => IMG_FLIP_BOTH,
        });

        return $image;
    }
}
