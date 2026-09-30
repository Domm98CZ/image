<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Geometry;

use Domm98CZ\Image\Exception\InvalidAngleException;
use Domm98CZ\Image\Geometry\Angle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AngleTest extends TestCase
{
    /** @return iterable<string, array{Angle, float}> */
    public static function normalizations(): iterable
    {
        yield 'plain clockwise' => [Angle::clockwise(90), 90.0];
        yield 'counter clockwise' => [Angle::counterClockwise(90), 270.0];
        yield 'full turn' => [Angle::clockwise(360), 0.0];
        yield 'multiple turns' => [Angle::clockwise(810), 90.0];
        yield 'negative multiple turns' => [Angle::clockwise(-450), 270.0];
        yield 'negative zero' => [Angle::clockwise(-0.0), 0.0];
        yield 'tiny negative' => [Angle::clockwise(-1e-20), 0.0];
    }

    #[DataProvider('normalizations')]
    public function testNormalizesToHalfOpenFullTurn(Angle $angle, float $expected): void
    {
        self::assertSame($expected, $angle->clockwiseDegrees);
    }

    public function testRejectsNonFiniteValues(): void
    {
        $this->expectException(InvalidAngleException::class);

        Angle::clockwise(NAN);
    }

    public function testRightAngleMultiplesAndZero(): void
    {
        self::assertTrue(Angle::clockwise(0)->isZero());
        self::assertTrue(Angle::clockwise(180)->isRightAngleMultiple());
        self::assertTrue(Angle::counterClockwise(90)->isRightAngleMultiple());
        self::assertFalse(Angle::clockwise(45)->isRightAngleMultiple());
    }

    public function testEquality(): void
    {
        self::assertTrue(Angle::clockwise(-90)->equals(Angle::counterClockwise(90)));
        self::assertEqualsWithDelta(M_PI / 2, Angle::clockwise(90)->radians(), 1e-12);
    }
}
