<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

enum Interpolation
{
    case Nearest;
    case Bilinear;
    case Bicubic;
    case Lanczos;
}
