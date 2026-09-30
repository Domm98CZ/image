<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Exception\InvalidDimensionsException;

final readonly class Scale implements CompositeOperationInterface
{
    public function __construct(
        public float $factor,
        public Interpolation $interpolation = Interpolation::Lanczos,
    ) {
        if (!is_finite($factor) || $factor <= 0.0) {
            throw InvalidDimensionsException::invalidScaleFactor($factor);
        }
    }

    public function expand(ExpansionContext $context): array
    {
        return ResizeTo::dimensions($context->dimensions, $context->dimensions->scaledBy($this->factor), $this->interpolation);
    }

    public function requiredPrimitives(): array
    {
        return [Resize::class];
    }
}
