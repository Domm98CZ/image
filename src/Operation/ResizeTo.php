<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

/** @internal Shared by composites that resolve to at most one Resize. */
final class ResizeTo
{
    /** @return list<OperationInterface> */
    public static function dimensions(Dimensions $current, Dimensions $target, Interpolation $interpolation = Interpolation::Lanczos): array
    {
        return $current->equals($target) ? [] : [new Resize($target, $interpolation)];
    }
}
