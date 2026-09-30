<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Exception\InvalidDimensionsException;

final readonly class ScaleToHeight implements CompositeOperationInterface
{
    public function __construct(
        public int $height,
        public Interpolation $interpolation = Interpolation::Lanczos,
    ) {
        if ($height < 1) {
            throw InvalidDimensionsException::notPositive(1, $height);
        }
    }

    public function expand(ExpansionContext $context): array
    {
        return ResizeTo::dimensions($context->dimensions, $context->dimensions->scaledToHeight($this->height), $this->interpolation);
    }

    public function requiredPrimitives(): array
    {
        return [Resize::class];
    }
}
