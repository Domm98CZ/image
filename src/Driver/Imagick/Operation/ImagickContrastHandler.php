<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Contrast;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Contrast> */
final readonly class ImagickContrastHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Contrast::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        $factor = $operation->factor();
        // @phpstan-ignore argument.type (stub lists single channels; ImageMagick takes the R|G|B bitmask, verified in integration tests)
        $image->functionImage(Imagick::FUNCTION_POLYNOMIAL, [$factor, 0.5 - 0.5 * $factor], ImagickChannels::RGB);

        return $image;
    }
}
