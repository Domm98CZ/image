<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Driver\Gd\GdDriver;
use Domm98CZ\Image\Driver\Imagick\ImagickDriver;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Pipeline\CostModel;
use Domm98CZ\Image\Pipeline\Transfer;
use Domm98CZ\Image\Security\InputGuard;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Tests\Support\GdFixtures;
use Domm98CZ\Image\Tests\Support\InkAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('gd')]
#[RequiresPhpExtension('imagick')]
final class CrossDriverTest extends TestCase
{
    use InkAssertions;

    private const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf';
    private const FONT_SANS = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

    // Data providers run before requirements are checked, so drivers are only constructed inside the test.
    /** @return iterable<string, array{DriverName, DriverName}> */
    public static function directions(): iterable
    {
        yield 'gd to imagick' => [DriverName::Gd, DriverName::Imagick];
        yield 'imagick to gd' => [DriverName::Imagick, DriverName::Gd];
    }

    #[DataProvider('directions')]
    public function testTransferIsLosslessIncludingAlpha(DriverName $fromName, DriverName $toName): void
    {
        $from = self::driver($fromName);
        $to = self::driver($toName);
        $factory = new ImageFactory(Configuration::default(), new DriverRegistry([$from]));
        $image = $factory->openBytes(GdFixtures::quadrantsPng(40, 20, transparentBottomRight: true))->toImage();

        $moved = (new Transfer(new InputGuard(Limits::default())))->move($image->handle(), $from, $to);

        self::assertEquals(new Dimensions(40, 20), $to->dimensions($moved));
        foreach ([[5, 5], [35, 5], [5, 15]] as [$x, $y]) {
            self::assertTrue($to->colorAt($moved, new Point($x, $y))->equals($from->colorAt($image->handle(), new Point($x, $y))), sprintf('pixel %d,%d', $x, $y));
        }
        self::assertTrue($to->colorAt($moved, new Point(35, 15))->isFullyTransparent());
    }

    public function testDefaultFactoryDetectsBothDriversAndPrefersGdForPlainWork(): void
    {
        $image = (new ImageFactory())->create(new Dimensions(10, 10), Color::black())->scale(2.0)->toImage();

        self::assertSame(DriverName::Gd, $image->driverName());
        self::assertSame([DriverName::Gd, DriverName::Imagick], DriverRegistry::detect(Configuration::default())->names());
    }

    public function testAvifIsDecodedWithCorrectColoursWhateverImageMagickVersionIsInstalled(): void
    {
        $factory = new ImageFactory();
        $avif = $factory->create(new Dimensions(16, 16), Color::fromHex('#0000ff'))->encode(new AvifOutput(quality: 90));

        $color = $factory->openBytes($avif->bytes)->toImage()->colorAt(new Point(8, 8));

        self::assertGreaterThan(200, $color->blue, $color->toHex());
        self::assertLessThan(60, $color->red, $color->toHex());
    }

    public function testAvifOutputKeepsAlphaWhateverImageMagickVersionIsInstalled(): void
    {
        $avif = (new ImageFactory())->create(new Dimensions(16, 16), new Color(255, 0, 0, 128))->encode(new AvifOutput());

        self::assertTrue((new HeaderProbe())->probe(new BinaryString($avif->bytes))->hasAlpha);
    }

    public function testPixelFiltersRunOnImagickWhenBothDriversAreAvailable(): void
    {
        $image = (new ImageFactory())->create(new Dimensions(10, 10), Color::black())
            ->applyPixels(static fn(PixelBuffer $pixels): PixelBuffer => $pixels)
            ->toImage();

        self::assertSame(DriverName::Imagick, $image->driverName());
    }

    // An image already on GD keeps its PHP-fallback pixel step there under the reference prices; a machine where
    // the hand-off is cheap (the calibrated model) sends the same step to Imagick's native buffer access.
    public function testACalibratedCostModelChangesWhereAPixelStepRuns(): void
    {
        $reference = new ImageFactory();
        $calibrated = new ImageFactory(Configuration::default()->withCostModel(new CostModel(transferNanosPerPixel: 45.0)));
        $filter = static fn(PixelBuffer $pixels): PixelBuffer => $pixels;
        $onGd = $reference->create(new Dimensions(10, 10), Color::black())->toImage();

        self::assertSame(DriverName::Gd, $onGd->driverName());
        self::assertSame(DriverName::Gd, $reference->from($onGd)->applyPixels($filter)->toImage()->driverName());
        self::assertSame(DriverName::Imagick, $calibrated->from($onGd)->applyPixels($filter)->toImage()->driverName());
    }

    public function testForcedImagickIsUsedEvenWhenGdIsAvailable(): void
    {
        $image = (new ImageFactory(Configuration::default()->withForcedDriver(DriverName::Imagick)))->create(new Dimensions(3, 3))->toImage();

        self::assertSame(DriverName::Imagick, $image->driverName());
    }

    /** @return iterable<string, array{list<DriverName>}> */
    public static function registryOrders(): iterable
    {
        yield 'gd first (detected order)' => [[DriverName::Gd, DriverName::Imagick]];
        yield 'imagick first' => [[DriverName::Imagick, DriverName::Gd]];
    }

    // Text used to be measured on the first driver while the planner drew on Imagick, whose glyph advances differ.
    /** @param list<DriverName> $order */
    #[DataProvider('registryOrders')]
    public function testTextIsMeasuredByTheDriverThatDrawsItWhateverTheRegistryOrder(array $order): void
    {
        if (!is_file(self::FONT)) {
            self::markTestSkipped('DejaVu Sans Mono is not installed (Debian package fonts-dejavu-core).');
        }
        $factory = new ImageFactory(Configuration::default(), new DriverRegistry(array_map(self::driver(...), $order)));
        $font = new Font(self::FONT, 64);

        $tight = $factory->text(self::PANGRAM, $font, Color::black());
        $padded = $factory->text(self::PANGRAM, $font, Color::black(), padding: 24);

        self::assertSame(DriverName::Imagick, $tight->driverName(), 'native drawing wins over GD\'s degraded one');
        self::assertTextImageIsTight($tight, $padded, 24);
    }

    // The same placeholder must appear at the same place whichever driver ends up drawing the text.
    public function testAGlyphTheFontLacksLooksTheSameOnBothDrivers(): void
    {
        $masks = self::drawnOnBothDrivers("a\u{4E38}b", self::font(self::FONT, 48));

        self::assertEqualsWithDelta(self::maskBox($masks['gd']), self::maskBox($masks['imagick']), 2, 'ink box');
        self::assertGreaterThan(0.9, self::overlap($masks['gd'], $masks['imagick']), 'GD ink covered by Imagick ink within one pixel');
        self::assertGreaterThan(0.9, self::overlap($masks['imagick'], $masks['gd']), 'Imagick ink covered by GD ink within one pixel');
    }

    // libgd cannot be handed a code point beyond the BMP: GD paints the placeholder where ImageMagick paints the emoji, so
    // the shapes differ but the text keeps the same layout (one advance, not four Latin-1 glyphs).
    public function testAnEmojiKeepsTheSameLayoutOnBothDrivers(): void
    {
        $masks = self::drawnOnBothDrivers("a\u{1F600}b", self::font(self::FONT_SANS, 48));

        [$gdLeft, , $gdRight, $gdBottom] = self::maskBox($masks['gd']);
        [$imagickLeft, , $imagickRight, $imagickBottom] = self::maskBox($masks['imagick']);
        self::assertEqualsWithDelta($imagickLeft, $gdLeft, 2, 'left edge');
        self::assertEqualsWithDelta($imagickRight, $gdRight, 8, 'right edge: b sits after one glyph on both drivers');
        self::assertEqualsWithDelta($imagickBottom, $gdBottom, 2, 'baseline');
    }

    // "\n" used to be left to the renderers, whose line pitches differ (1.05 em on GD, the font's metrics on ImageMagick):
    // the same paragraph came out ~12 % taller on Imagick. Both now stack the lines at the library's pitch.
    public function testAParagraphIsAsTallOnBothDrivers(): void
    {
        $font = self::font(self::FONT_SANS, 28);
        $paragraph = "Příliš žluťoučký kůň\núpěl ďábelské ódy,\na to hned šestkrát\nza sebou v řadě\ntěsně pod sebou\nna posledním řádku.";

        $heights = [];
        $widths = [];
        foreach ([DriverName::Gd, DriverName::Imagick] as $name) {
            $factory = new ImageFactory(Configuration::default()->withForcedDriver($name), new DriverRegistry([self::driver($name)]));
            $image = $factory->text($paragraph, $font, Color::black());
            self::assertSame($name, $image->driverName());
            $heights[$name->value] = $image->height();
            $widths[$name->value] = $image->width();
        }

        // Five pitches of 33.6 px plus the ink of one line (~33 px): the drivers' single-line ink boxes differ by a pixel or two.
        self::assertEqualsWithDelta($heights['imagick'], $heights['gd'], 3, 'canvas height');
        self::assertEqualsWithDelta(5 * 33.6 + 33, $heights['gd'], 4, 'height follows the pitch');
        self::assertEqualsWithDelta($widths['imagick'], $widths['gd'], 0.03 * $widths['imagick'], 'canvas width (advance rounding differs)');
    }

    /** @return array{gd: list<string>, imagick: list<string>} dark masks of the text drawn at the same spot on each driver */
    private static function drawnOnBothDrivers(string $text, Font $font): array
    {
        $masks = [];
        foreach ([DriverName::Gd, DriverName::Imagick] as $name) {
            $factory = new ImageFactory(Configuration::default()->withForcedDriver($name), new DriverRegistry([self::driver($name)]));
            $image = $factory->create(new Dimensions(200, 80), Color::white())
                ->draw(static fn(Canvas $c): Canvas => $c->text($text, new Point(10, 60), $font, Color::black()))
                ->toImage();
            self::assertSame($name, $image->driverName());
            $masks[$name->value] = self::darkMask($image);
        }

        return ['gd' => $masks['gd'], 'imagick' => $masks['imagick']];
    }

    private static function font(string $path, float $size): Font
    {
        if (!is_file($path)) {
            self::markTestSkipped(basename($path) . ' is not installed (Debian package fonts-dejavu-core).');
        }

        return new Font($path, $size);
    }

    /** @return list<string> one row per line, "1" where the pixel is darker than mid-grey */
    private static function darkMask(Image $image): array
    {
        $pixels = $image->pixels();
        $width = $pixels->dimensions->width;
        $mask = [];
        for ($y = 0; $y < $pixels->dimensions->height; ++$y) {
            $row = str_repeat('0', $width);
            for ($x = 0; $x < $width; ++$x) {
                if (ord($pixels->bytes[($y * $width + $x) * 4]) < 128) {
                    $row[$x] = '1';
                }
            }
            $mask[] = $row;
        }

        return $mask;
    }

    /**
     * @param list<string> $mask
     * @return array{int, int, int, int}
     */
    private static function maskBox(array $mask): array
    {
        [$left, $top, $right, $bottom] = [PHP_INT_MAX, PHP_INT_MAX, -1, -1];
        foreach ($mask as $y => $row) {
            $first = strpos($row, '1');
            if ($first === false) {
                continue;
            }
            [$left, $top, $right, $bottom] = [min($left, $first), min($top, $y), max($right, (int) strrpos($row, '1')), $y];
        }
        self::assertGreaterThan(-1, $right, 'something was drawn');

        return [$left, $top, $right, $bottom];
    }

    /**
     * Share of ink pixels in $a with ink in $b at most one pixel away.
     *
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function overlap(array $a, array $b): float
    {
        $hits = 0;
        $total = 0;
        foreach ($a as $y => $row) {
            for ($x = strpos($row, '1'); $x !== false; $x = strpos($row, '1', $x + 1)) {
                ++$total;
                foreach ([-1, 0, 1] as $dy) {
                    $near = $b[$y + $dy] ?? '';
                    if (strpos(substr($near, max(0, $x - 1), 3), '1') !== false) {
                        ++$hits;
                        break;
                    }
                }
            }
        }

        return $total === 0 ? 0.0 : $hits / $total;
    }

    private static function driver(DriverName $name): DriverInterface
    {
        return $name === DriverName::Gd ? new GdDriver() : new ImagickDriver();
    }
}
