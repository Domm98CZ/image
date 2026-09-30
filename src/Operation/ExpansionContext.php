<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Metadata\Orientation;

final readonly class ExpansionContext
{
    public function __construct(
        public Dimensions $dimensions,
        public Orientation $orientation = Orientation::TopLeft,
    ) {}
}
