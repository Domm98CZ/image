<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Support;

use Domm98CZ\Image\Color\Color;

trait ColorAssertions
{
    // Per-channel slack for resampling and encoder rounding differences between drivers.
    private const COLOR_TOLERANCE = 3;

    /** @param array{int, int, int} $expected */
    private static function assertColorNear(array $expected, Color $actual, int $tolerance = self::COLOR_TOLERANCE): void
    {
        $message = sprintf('expected rgb(%d, %d, %d), got %s', $expected[0], $expected[1], $expected[2], $actual->toHex());
        self::assertEqualsWithDelta($expected, [$actual->red, $actual->green, $actual->blue], $tolerance, $message);
    }
}
