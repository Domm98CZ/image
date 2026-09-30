<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Dimensions;

final readonly class FitInside implements CompositeOperationInterface
{
    public function __construct(
        public Dimensions $box,
        public bool $upscale = false,
        public Interpolation $interpolation = Interpolation::Lanczos,
    ) {}

    public function expand(ExpansionContext $context): array
    {
        $current = $context->dimensions;
        if (!$this->upscale && $this->box->contains($current)) {
            return [];
        }

        return ResizeTo::dimensions($current, $current->fitInside($this->box), $this->interpolation);
    }

    public function requiredPrimitives(): array
    {
        return [Resize::class];
    }
}
