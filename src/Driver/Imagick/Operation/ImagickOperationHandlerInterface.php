<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;

/** @template T of PrimitiveOperationInterface */
interface ImagickOperationHandlerInterface
{
    /** @return class-string<T> */
    public function operation(): string;

    public function support(): Support;

    /** @param T $operation */
    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick;
}
