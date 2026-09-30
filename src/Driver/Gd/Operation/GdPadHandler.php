<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Driver\Gd\GdCanvas;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Pad;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use GdImage;

/** @implements GdOperationHandlerInterface<Pad> */
final readonly class GdPadHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Pad::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        $canvas = GdCanvas::blank($operation->resultingDimensions(GdCanvas::dimensions($image)), $operation->background);
        imagecopy($canvas, $image, $operation->left, $operation->top, 0, 0, imagesx($image), imagesy($image));

        return $canvas;
    }
}
