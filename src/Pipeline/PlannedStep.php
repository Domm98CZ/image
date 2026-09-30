<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Pipeline;

use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Operation\OperationInterface;

final readonly class PlannedStep
{
    public function __construct(
        public OperationInterface $operation,
        public DriverInterface $driver,
    ) {}
}
