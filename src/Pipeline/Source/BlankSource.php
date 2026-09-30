<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Pipeline\Source;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Geometry\Dimensions;

final readonly class BlankSource implements SourceInterface
{
    public function __construct(
        public Dimensions $dimensions,
        public Color $background,
    ) {}
}
