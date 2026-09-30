<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Imagick\ImagickColor;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Operation\Rotate;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Rotate> */
final readonly class ImagickRotateHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Rotate::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        if ($operation->angle->isZero()) {
            return $image;
        }
        if ($operation->angle->isRightAngleMultiple()) {
            $image->rotateImage(ImagickColor::toPixel($operation->background), $operation->angle->clockwiseDegrees);
            $image->setImagePage(0, 0, 0, 0);

            return $image;
        }
        if (!$operation->background->isOpaque() && !$image->getImageAlphaChannel()) {
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        }
        $target = $operation->resultingDimensions(new Dimensions($image->getImageWidth(), $image->getImageHeight()));
        $background = ImagickColor::toPixel($operation->background);
        $image->rotateImage($background, $operation->angle->clockwiseDegrees);
        $image->setImagePage(0, 0, 0, 0);
        // ImageMagick pads its bounding box by a few pixels; centre on the predicted size so every driver agrees.
        $image->setImageBackgroundColor($background);
        $image->extentImage(
            $target->width,
            $target->height,
            intdiv($image->getImageWidth() - $target->width, 2),
            intdiv($image->getImageHeight() - $target->height, 2),
        );

        return $image;
    }
}
