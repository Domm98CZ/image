<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Analysis;

use Domm98CZ\Image\Analysis\BlurHash;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use PHPUnit\Framework\TestCase;

final class BlurHashTest extends TestCase
{
    private const CHARACTERS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz#$%*+,-.:;=?@[]^_{|}~';

    public function testDcOnlyHashHasTheMinimumSixCharacterLength(): void
    {
        $hash = BlurHash::encode(PixelBuffer::filled(new Dimensions(16, 16), Color::fromHex('#ff8000')), 1, 1);

        self::assertSame(6, strlen($hash));
        self::assertMatchesRegularExpression('/^[' . preg_quote(self::CHARACTERS, '/') . ']{6}$/', $hash);
    }

    public function testDefaultComponentCountProducesTwentyEightCharacters(): void
    {
        $hash = BlurHash::encode(PixelBuffer::filled(new Dimensions(32, 32), Color::fromHex('#336699')));

        self::assertSame(28, strlen($hash));
    }

    public function testIsDeterministic(): void
    {
        $pixels = PixelBuffer::filled(new Dimensions(20, 12), Color::fromHex('#abcdef'));

        self::assertSame(BlurHash::encode($pixels), BlurHash::encode($pixels));
    }

    public function testDcTermDecodesBackToApproximatelyTheOriginalSolidColor(): void
    {
        $hash = BlurHash::encode(PixelBuffer::filled(new Dimensions(16, 16), Color::fromHex('#ff8000')), 1, 1);

        [$red, $green, $blue] = self::decodeDc($hash);
        self::assertEqualsWithDelta(0xFF, $red, 1);
        self::assertEqualsWithDelta(0x80, $green, 1);
        self::assertEqualsWithDelta(0x00, $blue, 1);
    }

    public function testRejectsComponentCountsOutsideOneToNine(): void
    {
        $this->expectException(InvalidOperationException::class);

        BlurHash::encode(PixelBuffer::filled(new Dimensions(4, 4), Color::black()), 10, 3);
    }

    /** @return array{0:int,1:int,2:int} */
    private static function decodeDc(string $hash): array
    {
        $value = 0;
        for ($i = 2; $i < 6; ++$i) {
            $value = $value * 83 + strpos(self::CHARACTERS, $hash[$i]);
        }

        return [($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF];
    }
}
