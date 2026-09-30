<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

final readonly class FitOutside implements CompositeOperationInterface
{
    public function __construct(
        public Dimensions $box,
        public bool $upscale = false,
        public Interpolation $interpolation = Interpolation::Lanczos,
    ) {}

    public function expand(ExpansionContext $context): array
    {
        $current = $context->dimensions;
        $target = $current->fitOutside($this->box);
        if (!$this->upscale && $target->pixelCount() > $current->pixelCount()) {
            return [];
        }

        return ResizeTo::dimensions($current, $target, $this->interpolation);
    }

    public function requiredPrimitives(): array
    {
        return [Resize::class];
    }
}
