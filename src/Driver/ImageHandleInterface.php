<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver;

// Opaque, driver-owned pixel storage; only the owning driver may look inside.
interface ImageHandleInterface
{
    public function driver(): DriverName;
}
