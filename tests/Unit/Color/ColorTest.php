<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Color;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Exception\InvalidColorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    /** @return iterable<string, array{int, int, int, int}> */
    public static function outOfRangeChannels(): iterable
    {
        yield 'red high' => [256, 0, 0, 255];
        yield 'green negative' => [0, -1, 0, 255];
    }

    #[DataProvider('outOfRangeChannels')]
    public function testRejectsChannelsOutOfRange(int $red, int $green, int $blue, int $alpha): void
    {
        $this->expectException(InvalidColorException::class);

        new Color($red, $green, $blue, $alpha);
    }

    /** @return iterable<string, array{string, Color}> */
    public static function hexColors(): iterable
    {
        yield 'short with alpha' => ['#f0a8', new Color(255, 0, 170, 136)];
        yield 'without hash' => ['ffffff', Color::white()];
    }

    #[DataProvider('hexColors')]
    public function testParsesHex(string $hex, Color $expected): void
    {
        self::assertTrue(Color::fromHex($hex)->equals($expected));
    }

    /** @return iterable<string, array{string}> */
    public static function malformedHex(): iterable
    {
        yield 'five digits' => ['#12345'];
        yield 'non hex' => ['#ggg'];
    }

    #[DataProvider('malformedHex')]
    public function testRejectsMalformedHex(string $hex): void
    {
        $this->expectException(InvalidColorException::class);

        Color::fromHex($hex);
    }

    public function testFormatsHexOmittingOpaqueAlpha(): void
    {
        self::assertSame('#1a2b3c', Color::rgb(26, 43, 60)->toHex());
        self::assertSame('#1a2b3c80', Color::rgb(26, 43, 60)->withAlpha(128)->toHex());
    }

    public function testOpacityConversion(): void
    {
        $color = Color::rgba(10, 20, 30, 0.5);

        self::assertSame(128, $color->alpha);
        self::assertEqualsWithDelta(0.5, $color->opacity(), 0.01);
        self::assertTrue(Color::black()->isOpaque());
        self::assertTrue(Color::transparent()->isFullyTransparent());
    }

    /** @return iterable<string, array{float}> */
    public static function invalidOpacities(): iterable
    {
        yield 'negative' => [-0.1];
        yield 'nan' => [NAN];
    }

    #[DataProvider('invalidOpacities')]
    public function testRejectsInvalidOpacity(float $opacity): void
    {
        $this->expectException(InvalidColorException::class);

        Color::white()->withOpacity($opacity);
    }
}
