<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\Colorize;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;

/** @implements ImagickOperationHandlerInterface<Colorize> */
final readonly class ImagickColorizeHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Colorize::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        $strength = $operation->strength;
        $tint = $operation->tint;
        foreach ([Imagick::CHANNEL_RED => $tint->red, Imagick::CHANNEL_GREEN => $tint->green, Imagick::CHANNEL_BLUE => $tint->blue] as $channel => $value) {
            $image->functionImage(Imagick::FUNCTION_POLYNOMIAL, [1 - $strength, $strength * $value / 255], $channel);
        }

        return $image;
    }
}
