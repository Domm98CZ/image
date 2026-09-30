<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Driver\DriverName;

final class UnsupportedOperationException extends UnsupportedFeatureException
{
    /** @param list<DriverName> $drivers */
    public static function noDriverSupports(string $operation, array $drivers): self
    {
        return new self(sprintf(
            'No available driver supports operation %s (available: %s).',
            $operation,
            self::names($drivers),
        ));
    }

    /** @param list<DriverName> $drivers */
    public static function noExecutionPlan(array $drivers): self
    {
        return new self(sprintf('No combination of the available drivers (%s) can run this job.', self::names($drivers)));
    }

    /** @param list<DriverName> $drivers */
    private static function names(array $drivers): string
    {
        return $drivers === [] ? 'none' : implode(', ', array_map(static fn(DriverName $driver): string => $driver->value, $drivers));
    }
}
