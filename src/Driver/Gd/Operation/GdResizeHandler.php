<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Driver\Gd\GdCanvas;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Operation\Resize;
use GdImage;

/** @implements GdOperationHandlerInterface<Resize> */
final readonly class GdResizeHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Resize::class;
    }

    // Every filter runs natively; Bicubic and Lanczos are approximated by the resampled path GD
    // already has, the same way GdCompositeHandler stays Native for blend modes it approximates.
    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        $target = $operation->target;
        $resized = GdCanvas::blank($target, Color::transparent());
        if ($operation->interpolation === \Domm98CZ\Image\Operation\Interpolation::Nearest) {
            imagecopyresized($resized, $image, 0, 0, 0, 0, $target->width, $target->height, imagesx($image), imagesy($image));
        } else {
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $target->width, $target->height, imagesx($image), imagesy($image));
        }

        return $resized;
    }
}
