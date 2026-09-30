<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Brightness;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Brightness> */
final readonly class ImagickBrightnessHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Brightness::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        // @phpstan-ignore argument.type (stub lists single channels; ImageMagick takes the R|G|B bitmask, verified in integration tests)
        $image->functionImage(Imagick::FUNCTION_POLYNOMIAL, [1.0, $operation->offset() / 255], ImagickChannels::RGB);

        return $image;
    }
}
