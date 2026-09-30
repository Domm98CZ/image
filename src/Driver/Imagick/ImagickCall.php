<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick;

use Closure;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Exception\DriverOperationFailedException;
use ImagickException;
use ImagickPixelException;

/** @internal Turns ImageMagick's own exceptions into library exceptions at the seam. */
final class ImagickCall
{
    /**
     * @template T
     * @param Closure(): T $call
     * @return T
     */
    public static function run(string $action, Closure $call): mixed
    {
        try {
            return $call();
        } catch (ImagickException|ImagickPixelException $exception) {
            throw DriverOperationFailedException::because(DriverName::Imagick, $action, $exception->getMessage());
        }
    }
}
