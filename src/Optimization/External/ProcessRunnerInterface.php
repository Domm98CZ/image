<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Optimization\External;

interface ProcessRunnerInterface
{
    // $command is an argument vector executed without a shell: nothing in it is ever interpreted.
    /** @param non-empty-list<string> $command */
    public function run(array $command, string $stdin, float $timeoutSeconds, int $maxOutputBytes): ProcessResult;
}
