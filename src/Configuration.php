<?php

declare(strict_types=1);

namespace Domm98CZ\Image;

use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Pipeline\CostModel;
use Domm98CZ\Image\Security\Limits;

final readonly class Configuration
{
    public function __construct(
        public Limits $limits = new Limits(),
        public ?DriverName $forcedDriver = null,
        public CostModel $costModel = new CostModel(),
    ) {}

    public static function default(): self
    {
        return new self();
    }

    public function withLimits(Limits $limits): self
    {
        return new self($limits, $this->forcedDriver, $this->costModel);
    }

    public function withForcedDriver(DriverName $driver): self
    {
        return new self($this->limits, $driver, $this->costModel);
    }

    public function withoutForcedDriver(): self
    {
        return new self($this->limits, null, $this->costModel);
    }

    // Relative step and transfer prices the planner routes by; calibrate them for a machine whose drivers
    // perform unlike the reference measurements, e.g. a GPU-accelerated ImageMagick.
    public function withCostModel(CostModel $costModel): self
    {
        return new self($this->limits, $this->forcedDriver, $costModel);
    }
}
