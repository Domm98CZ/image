<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd;

use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\ImageHandleInterface;
use GdImage;

final readonly class GdImageHandle implements ImageHandleInterface
{
    public function __construct(
        public GdImage $image,
    ) {}

    public function driver(): DriverName
    {
        return DriverName::Gd;
    }
}
