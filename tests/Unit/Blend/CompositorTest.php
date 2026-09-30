<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Blend;

use Domm98CZ\Image\Blend\BlendMode;
use Domm98CZ\Image\Blend\Compositor;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompositorTest extends TestCase
{
    /** @return iterable<string, array{BlendMode, float, float, float}> */
    public static function mixes(): iterable
    {
        yield 'normal' => [BlendMode::Normal, 0.8, 0.2, 0.2];
        yield 'multiply' => [BlendMode::Multiply, 0.5, 0.5, 0.25];
        yield 'screen' => [BlendMode::Screen, 0.5, 0.5, 0.75];
        yield 'overlay dark backdrop' => [BlendMode::Overlay, 0.25, 0.5, 0.25];
        yield 'darken' => [BlendMode::Darken, 0.3, 0.7, 0.3];
        yield 'lighten' => [BlendMode::Lighten, 0.3, 0.7, 0.7];
        yield 'difference' => [BlendMode::Difference, 0.3, 0.7, 0.4];
        yield 'hard light light source' => [BlendMode::HardLight, 0.5, 0.75, 0.75];
        yield 'soft light neutral source' => [BlendMode::SoftLight, 0.4, 0.5, 0.4];
        yield 'color dodge' => [BlendMode::ColorDodge, 0.4, 0.5, 0.8];
        yield 'color burn' => [BlendMode::ColorBurn, 0.6, 0.5, 0.2];
        yield 'exclusion' => [BlendMode::Exclusion, 0.4, 0.5, 0.5];
    }

    #[DataProvider('mixes')]
    public function testSeparableBlendFunctions(BlendMode $mode, float $backdrop, float $source, float $expected): void
    {
        self::assertEqualsWithDelta($expected, $mode->mix($backdrop, $source), 1e-9);
    }

    public function testNormalWithOpacityOverOpaqueBackdrop(): void
    {
        $result = (new Compositor())->blend(self::pixel(255, 255, 255, 255), self::pixel(255, 0, 0, 255), BlendMode::Normal, 0.5);

        self::assertSame([255, 128, 128, 255], self::rgba($result));
    }

    public function testSourceOverTransparentBackdropKeepsSourceColor(): void
    {
        $result = (new Compositor())->blend(self::pixel(0, 0, 0, 0), self::pixel(10, 200, 30, 128), BlendMode::Multiply);

        self::assertSame([10, 200, 30, 128], self::rgba($result));
    }

    public function testFullyTransparentSourceChangesNothing(): void
    {
        $backdrop = self::pixel(1, 2, 3, 4);

        self::assertSame($backdrop->bytes, (new Compositor())->blend($backdrop, self::pixel(255, 255, 255, 0), BlendMode::Screen)->bytes);
    }

    public function testRejectsMismatchedBuffers(): void
    {
        $this->expectException(InvalidOperationException::class);

        (new Compositor())->blend(self::pixel(0, 0, 0, 0), new PixelBuffer(new Dimensions(2, 1), str_repeat("\0", 8)), BlendMode::Normal);
    }

    private static function pixel(int $r, int $g, int $b, int $a): PixelBuffer
    {
        return new PixelBuffer(new Dimensions(1, 1), pack('C4', $r, $g, $b, $a));
    }

    /** @return list<int> */
    private static function rgba(PixelBuffer $pixels): array
    {
        return array_values((array) unpack('C4', $pixels->bytes));
    }
}
