<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Geometry\Point;
use PHPUnit\Framework\Attributes\DataProvider;

// The full, exhaustive contract - only run against the reference (Imagick) driver, see DEC-010.
// GD gets the spot-check subset in ColorSpotCheckContractTestCase (parent of this class too).
abstract class ColorContractTestCase extends ColorSpotCheckContractTestCase
{
    /** @return iterable<string, array{float, int}> */
    public static function blurs(): iterable
    {
        yield 'sigma 1' => [1.0, 12];
        yield 'sigma 2' => [2.0, 12];
        yield 'sigma 3' => [3.0, 12];
        yield 'sigma 8 (GD approximates via downscale)' => [8.0, 25];
    }

    #[DataProvider('blurs')]
    public function testBlurFollowsAGaussianAcrossAnEdge(float $sigma, int $tolerance): void
    {
        $image = $this->factory->openBytes(self::edgePng(200))->blur($sigma)->toImage();

        foreach ([-2.0, -1.0, -0.5, 0.0, 0.5, 1.0, 2.0] as $offset) {
            $x = (int) (100 + $offset * $sigma);
            $expected = 255 * self::normalCdf(($x + 0.5 - 100) / $sigma);
            self::assertEqualsWithDelta($expected, $image->colorAt(new Point($x, 4))->red, $tolerance, sprintf('x=%d', $x));
        }
    }

    public function testSharpenOvershootsBothSidesOfAnEdgeAndLeavesFlatAreasAlone(): void
    {
        $image = $this->factory->openBytes(self::edgePng(40, [60, 60, 60], [190, 190, 190]))->sharpen(1.0)->toImage();

        self::assertEqualsWithDelta(28, $image->colorAt(new Point(19, 4))->red, 4);
        self::assertEqualsWithDelta(221, $image->colorAt(new Point(20, 4))->red, 4);
        self::assertEqualsWithDelta(60, $image->colorAt(new Point(10, 4))->red, 2);
        self::assertEqualsWithDelta(190, $image->colorAt(new Point(30, 4))->red, 2);
    }

    /**
     * @param array{int, int, int} $left
     * @param array{int, int, int} $right
     */
    private static function edgePng(int $width, array $left = [0, 0, 0], array $right = [255, 255, 255]): string
    {
        $image = imagecreatetruecolor($width, 8);
        self::assertNotFalse($image);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, 7, (int) imagecolorallocate($image, $left[0], $left[1], $left[2]));
        imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, 7, (int) imagecolorallocate($image, $right[0], $right[1], $right[2]));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    // Abramowitz & Stegun 7.1.26, accurate to ~1e-7: plenty for pixel-level comparisons.
    private static function normalCdf(float $z): float
    {
        $x = abs($z) / M_SQRT2;
        $t = 1 / (1 + 0.3275911 * $x);
        $erf = 1 - ((((1.061405429 * $t - 1.453152027) * $t + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$x * $x);

        return 0.5 * (1 + ($z < 0 ? -$erf : $erf));
    }
}
