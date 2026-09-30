<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Fake;

use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\ImageHandleInterface;
use Domm98CZ\Image\Geometry\Dimensions;

final readonly class FakeImageHandle implements ImageHandleInterface
{
    public function __construct(
        private DriverName $driver,
        public Dimensions $dimensions,
        public int $id,
    ) {}

    public function driver(): DriverName
    {
        return $this->driver;
    }
}
