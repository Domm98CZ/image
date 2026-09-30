<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Color;

use Closure;

final readonly class CallbackPixelFilter implements PixelFilterInterface
{
    /** @param Closure(PixelBuffer): PixelBuffer $callback */
    public function __construct(
        private Closure $callback,
    ) {}

    public function apply(PixelBuffer $pixels): PixelBuffer
    {
        return ($this->callback)($pixels);
    }
}
