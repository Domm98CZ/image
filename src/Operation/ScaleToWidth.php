<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Exception\InvalidDimensionsException;

final readonly class ScaleToWidth implements CompositeOperationInterface
{
    public function __construct(
        public int $width,
        public Interpolation $interpolation = Interpolation::Lanczos,
    ) {
        if ($width < 1) {
            throw InvalidDimensionsException::notPositive($width, 1);
        }
    }

    public function expand(ExpansionContext $context): array
    {
        return ResizeTo::dimensions($context->dimensions, $context->dimensions->scaledToWidth($this->width), $this->interpolation);
    }

    public function requiredPrimitives(): array
    {
        return [Resize::class];
    }
}
