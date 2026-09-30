<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Drawing;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Exception\InvalidDrawingException;

/** @internal */
final class Paint
{
    public static function assertSomething(string $shape, ?Color $fill, ?Stroke $stroke): void
    {
        if ($fill === null && $stroke === null) {
            throw InvalidDrawingException::nothingToPaint($shape);
        }
    }
}
