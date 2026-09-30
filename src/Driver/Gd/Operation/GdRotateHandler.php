<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\Gd\CapturedCall;
use Domm98CZ\Image\Driver\Gd\GdCanvas;
use Domm98CZ\Image\Driver\Gd\GdColor;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\DriverOperationFailedException;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Operation\Rotate;
use GdImage;

/** @implements GdOperationHandlerInterface<Rotate> */
final readonly class GdRotateHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Rotate::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        if ($operation->angle->isZero()) {
            return $image;
        }
        $background = GdColor::allocate($image, $operation->background);
        // GD rotates counter-clockwise.
        $counterClockwise = 360.0 - $operation->angle->clockwiseDegrees;
        $call = CapturedCall::run(static fn(): GdImage|false => imagerotate($image, $counterClockwise, $background));
        if (!$call->result instanceof GdImage) {
            throw DriverOperationFailedException::because(DriverName::Gd, 'rotate', $call->error);
        }
        $rotated = $call->result;
        GdCanvas::prepare($rotated);
        if ($operation->angle->isRightAngleMultiple()) {
            return $rotated;
        }

        // Centre on the predicted bounding box so every driver returns the same size for the same rotation.
        $target = $operation->resultingDimensions(GdCanvas::dimensions($image));
        $normalized = GdCanvas::blank($target, $operation->background);
        imagecopy(
            $normalized,
            $rotated,
            intdiv($target->width - imagesx($rotated), 2),
            intdiv($target->height - imagesy($rotated), 2),
            0,
            0,
            imagesx($rotated),
            imagesy($rotated),
        );

        return $normalized;
    }
}
