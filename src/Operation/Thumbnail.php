<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Dimensions;

final readonly class Thumbnail implements CompositeOperationInterface
{
    public function __construct(
        public Dimensions $size,
        public Anchor $focus = Anchor::Center,
        public bool $upscale = true,
        public Interpolation $interpolation = Interpolation::Lanczos,
    ) {}

    public function expand(ExpansionContext $context): array
    {
        return [
            new FitOutside($this->size, $this->upscale, $this->interpolation),
            new AnchoredCrop($this->size, $this->focus),
        ];
    }

    public function requiredPrimitives(): array
    {
        return [Resize::class, Crop::class];
    }
}
