<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver;

enum DriverName: string
{
    case Gd = 'gd';
    case Imagick = 'imagick';
}
