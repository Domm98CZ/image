<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Flip;
use Domm98CZ\Image\Operation\FlipDirection;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Flip> */
final readonly class ImagickFlipHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Flip::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        // ImageMagick names are inverted relative to ours: flop mirrors left-right, flip top-bottom.
        if ($operation->direction !== FlipDirection::Vertical) {
            $image->flopImage();
        }
        if ($operation->direction !== FlipDirection::Horizontal) {
            $image->flipImage();
        }

        return $image;
    }
}
