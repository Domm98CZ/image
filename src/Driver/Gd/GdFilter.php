<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd;

use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Exception\DriverOperationFailedException;
use GdImage;

/** @internal */
final class GdFilter
{
    public static function apply(GdImage $image, int $filter, int ...$arguments): void
    {
        $call = CapturedCall::run(static fn(): bool => imagefilter($image, $filter, ...$arguments));
        if ($call->result !== true) {
            throw DriverOperationFailedException::because(DriverName::Gd, sprintf('apply filter %d', $filter), $call->error);
        }
    }
}
