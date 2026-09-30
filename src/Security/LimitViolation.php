<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Security;

final readonly class LimitViolation
{
    public function __construct(
        public LimitType $limit,
        public int $actual,
        public int $allowed,
    ) {}
}
