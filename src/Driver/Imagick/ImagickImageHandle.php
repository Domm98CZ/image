<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick;

use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\ImageHandleInterface;
use Imagick;

final readonly class ImagickImageHandle implements ImageHandleInterface
{
    public function __construct(
        public Imagick $image,
    ) {}

    public function driver(): DriverName
    {
        return DriverName::Imagick;
    }
}
