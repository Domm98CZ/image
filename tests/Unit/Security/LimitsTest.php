<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Security;

use Domm98CZ\Image\Exception\InvalidConfigurationException;
use Domm98CZ\Image\Security\Limits;
use PHPUnit\Framework\TestCase;

final class LimitsTest extends TestCase
{
    public function testDefaultsMatchDocumentedValues(): void
    {
        $limits = Limits::default();

        self::assertSame(16_384, $limits->maxWidth);
        self::assertSame(16_384, $limits->maxHeight);
        self::assertSame(50_000_000, $limits->maxPixels);
        self::assertSame(500, $limits->maxFrames);
        self::assertSame(200_000_000, $limits->maxAnimationPixels);
        self::assertSame(52_428_800, $limits->maxInputBytes);
        self::assertTrue($limits->checkMemoryLimit);
    }

    public function testWithersChangeOnlyOneValue(): void
    {
        $limits = Limits::default()
            ->withMaxWidth(1)
            ->withMaxHeight(2)
            ->withMaxPixels(3)
            ->withMaxFrames(4)
            ->withMaxAnimationPixels(5)
            ->withMaxInputBytes(6)
            ->withMemoryLimitCheck(false);

        self::assertEquals(new Limits(1, 2, 3, 4, 5, 6, false), $limits);
        self::assertSame(16_384, Limits::default()->withMaxPixels(10)->maxWidth);
    }

    public function testRejectsNonPositiveLimits(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"frames"');

        Limits::default()->withMaxFrames(0);
    }
}
