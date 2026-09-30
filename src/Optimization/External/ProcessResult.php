<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Optimization\External;

final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {}
}
