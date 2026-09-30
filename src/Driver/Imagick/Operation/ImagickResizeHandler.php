<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Operation\Resize;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Resize> */
final readonly class ImagickResizeHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Resize::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        // resizeImage can drop the alpha-channel flag even when transparent samples remain.
        $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $image->resizeImage($operation->target->width, $operation->target->height, match ($operation->interpolation) {
            \Domm98CZ\Image\Operation\Interpolation::Nearest => Imagick::FILTER_POINT,
            \Domm98CZ\Image\Operation\Interpolation::Bilinear => Imagick::FILTER_TRIANGLE,
            \Domm98CZ\Image\Operation\Interpolation::Bicubic => Imagick::FILTER_CUBIC,
            \Domm98CZ\Image\Operation\Interpolation::Lanczos => Imagick::FILTER_LANCZOS,
        }, 1.0);
        $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        $image->setImagePage(0, 0, 0, 0);

        return $image;
    }
}
