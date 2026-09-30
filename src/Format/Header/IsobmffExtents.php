<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Header;

/** @internal Mutable accumulator for a single IsobmffHeaderProbe::probe() call. */
final class IsobmffExtents
{
    public int $maxWidth = 0;
    public int $maxHeight = 0;
    public bool $hasAlpha = false;
    public int $boxCount = 0;
}
