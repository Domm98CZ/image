<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

final class InvalidConfigurationException extends InvalidArgumentException
{
    public static function limitNotPositive(string $limit, int|float $value): self
    {
        return new self(sprintf('Limit "%s" must be positive, got %s.', $limit, self::number($value)));
    }

    public static function costNotNonNegative(string $cost, float $value): self
    {
        return new self(sprintf('Cost "%s" must be a non-negative number, got %s.', $cost, self::number($value)));
    }

    public static function degradedCostNotHighest(string $cost, float $value, float $degraded): self
    {
        return new self(sprintf(
            'Cost "degradedNanosPerPixel" must exceed every other cost, got %s while "%s" is %s.',
            self::number($degraded),
            $cost,
            self::number($value),
        ));
    }
}
