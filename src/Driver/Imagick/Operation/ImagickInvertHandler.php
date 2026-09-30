<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Invert;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Invert> */
final readonly class ImagickInvertHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Invert::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        // @phpstan-ignore argument.type (stub lists single channels; ImageMagick takes the R|G|B bitmask, verified in integration tests)
        $image->negateImage(false, ImagickChannels::RGB);

        return $image;
    }
}
