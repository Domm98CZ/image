<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use GdImage;

/** @template T of PrimitiveOperationInterface */
interface GdOperationHandlerInterface
{
    /** @return class-string<T> */
    public function operation(): string;

    public function support(): Support;

    /** @param T $operation */
    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage;
}
