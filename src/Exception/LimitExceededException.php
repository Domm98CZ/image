<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Security\LimitViolation;

final class LimitExceededException extends InvalidInputException
{
    public function __construct(
        public readonly LimitViolation $violation,
    ) {
        parent::__construct(sprintf(
            'Limit exceeded: %s is %d, the configured maximum is %d.',
            $violation->limit->description(),
            $violation->actual,
            $violation->allowed,
        ));
    }
}
