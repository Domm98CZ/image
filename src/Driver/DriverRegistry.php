<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver;

use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\Gd\GdDriver;
use Domm98CZ\Image\Driver\Imagick\ImagickDriver;
use Domm98CZ\Image\Exception\DriverNotAvailableException;

final readonly class DriverRegistry
{
    /** @var non-empty-list<DriverInterface> */
    public array $drivers;

    // Order is preference: the first driver able to run a whole plan wins.
    /** @param list<DriverInterface> $drivers */
    public function __construct(array $drivers)
    {
        if ($drivers === []) {
            throw DriverNotAvailableException::none();
        }
        $this->drivers = $drivers;
    }

    public static function detect(Configuration $configuration): self
    {
        $available = [];
        if (GdDriver::isAvailable()) {
            $available[] = new GdDriver([], $configuration->limits);
        }
        if (ImagickDriver::isAvailable()) {
            $available[] = new ImagickDriver($configuration->limits);
        }

        return (new self($available))->restrictedTo($configuration);
    }

    public function restrictedTo(Configuration $configuration): self
    {
        if ($configuration->forcedDriver === null) {
            return $this;
        }
        foreach ($this->drivers as $driver) {
            if ($driver->name() === $configuration->forcedDriver) {
                return new self([$driver]);
            }
        }

        throw DriverNotAvailableException::forced($configuration->forcedDriver);
    }

    public function get(DriverName $name): ?DriverInterface
    {
        foreach ($this->drivers as $driver) {
            if ($driver->name() === $name) {
                return $driver;
            }
        }

        return null;
    }

    /** @return list<DriverName> */
    public function names(): array
    {
        return array_map(static fn(DriverInterface $driver): DriverName => $driver->name(), $this->drivers);
    }
}
