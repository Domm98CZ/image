<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Pipeline;

use Domm98CZ\Image\Driver\DriverInterface;

final readonly class Plan
{
    /** @param list<PlannedStep> $steps */
    public function __construct(
        public DriverInterface $sourceDriver,
        public array $steps,
        public ?DriverInterface $encoder = null,
    ) {}

    public function finalDriver(): DriverInterface
    {
        if ($this->encoder !== null) {
            return $this->encoder;
        }

        return $this->steps === [] ? $this->sourceDriver : $this->steps[array_key_last($this->steps)]->driver;
    }

    public function transferCount(): int
    {
        $transfers = 0;
        $current = $this->sourceDriver;
        foreach ($this->steps as $step) {
            if ($step->driver !== $current) {
                ++$transfers;
                $current = $step->driver;
            }
        }

        return $this->encoder !== null && $this->encoder !== $current ? $transfers + 1 : $transfers;
    }
}
