<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

final readonly class AutoOrient implements CompositeOperationInterface
{
    public function expand(ExpansionContext $context): array
    {
        return $context->orientation->corrections();
    }

    public function requiredPrimitives(): array
    {
        return [Rotate::class, Flip::class];
    }
}
