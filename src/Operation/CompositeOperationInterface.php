<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

interface CompositeOperationInterface extends OperationInterface
{
    /** @return list<OperationInterface> */
    public function expand(ExpansionContext $context): array;

    /** @return list<class-string<PrimitiveOperationInterface>> */
    public function requiredPrimitives(): array;
}
