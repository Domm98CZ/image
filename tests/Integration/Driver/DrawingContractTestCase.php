<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\Stroke;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\Tests\Support\InkAssertions;
use PHPUnit\Framework\Attributes\DataProvider;

// The full, exhaustive contract - only run against the reference (Imagick) driver, see DEC-010.
// GD gets the spot-check subset in DrawingSpotCheckContractTestCase (parent of this class too).
abstract class DrawingContractTestCase extends DrawingSpotCheckContractTestCase
{
    use InkAssertions;

    public function testStrokedRectangleLeavesItsInsideAlone(): void
    {
        $image = $this->drawOnWhite(50, 40, static fn(Canvas $c) => $c->rectangle(new Rectangle(new Point(10, 10), new Dimensions(20, 10)), stroke: new Stroke(Color::black(), 1)));

        self::assertLessThan(100, $image->colorAt(new Point(10, 15))->red);
        self::assertLessThan(100, $image->colorAt(new Point(20, 19))->red);
        self::assertSame(255, $image->colorAt(new Point(20, 15))->red);
    }

    public function testPolygonFillsItsInteriorOnly(): void
    {
        $image = $this->drawOnWhite(50, 50, static fn(Canvas $c) => $c->polygon([new Point(5, 45), new Point(25, 5), new Point(45, 45)], Color::black()));

        self::assertLessThan(50, $image->colorAt(new Point(25, 30))->red);
        self::assertSame(255, $image->colorAt(new Point(6, 6))->red);
        self::assertSame(255, $image->colorAt(new Point(44, 6))->red);
    }

    public function testSemiTransparentFillBlendsOverTheImage(): void
    {
        $image = $this->drawOnWhite(20, 20, static fn(Canvas $c) => $c->rectangle(Rectangle::covering(new Dimensions(20, 20)), Color::rgba(255, 0, 0, 0.5)));

        $color = $image->colorAt(new Point(10, 10));
        self::assertSame([255, 255], [$color->red, $color->alpha]);
        self::assertEqualsWithDelta(127, $color->green, 3);
        self::assertEqualsWithDelta(127, $color->blue, 3);
    }

    public function testRotatedTextRunsAlongTheAngle(): void
    {
        $font = $this->font(30);

        $image = $this->drawOnWhite(100, 300, static fn(Canvas $c) => $c->text('Rotated', new Point(40, 20), $font, Color::black(), Angle::clockwise(90)));

        [$left, $top, $right, $bottom] = self::inkBox($image);
        self::assertGreaterThan(3 * ($right - $left), $bottom - $top);
        self::assertGreaterThanOrEqual(15, $top);
    }

    /** @return iterable<string, array{string}> */
    public static function supplementaryPlaneCharacters(): iterable
    {
        // Both are in DejaVu Sans; ImageMagick paints their glyphs, GD cannot reach any glyph beyond the BMP and paints the placeholder.
        yield 'U+10300 Old Italic letter A' => ["\u{10300}"];
        yield 'U+1F600 grinning face' => ["\u{1F600}"];
    }

    // libgd reads UTF-8 only up to three bytes: left alone, a four-byte character came out as four Latin-1 glyphs.
    #[DataProvider('supplementaryPlaneCharacters')]
    public function testACharacterBeyondTheBasicMultilingualPlaneTakesTheRoomOfOneGlyph(string $character): void
    {
        $font = $this->font(40);

        $alone = $this->factory->text($character, $font, Color::black());
        $between = $this->factory->text('a' . $character . 'b', $font, Color::black());
        $plain = $this->factory->text('ab', $font, Color::black());

        self::assertSame($this->driver()->name(), $alone->driverName());
        self::assertEqualsWithDelta(40, $alone->width(), 20, 'one glyph of roughly an em');
        self::assertEqualsWithDelta(40, $between->width() - $plain->width(), 20, 'adds roughly one advance between a and b');
    }

    // libgd expands HTML entities in plain text; ImageMagick does not, and neither should the text API.
    public function testAmpersandsAndEntityLikeTextAreDrawnLiterally(): void
    {
        $font = $this->font(40);

        $ampersand = $this->factory->text('&', $font, Color::black());
        $spelled = $this->factory->text('&amp;', $font, Color::black());
        $numeric = $this->factory->text('&#8364;', $font, Color::black());
        $euro = $this->factory->text("\u{20AC}", $font, Color::black());

        self::assertGreaterThan(3 * $ampersand->width(), $spelled->width(), '&amp; is five glyphs, not one');
        self::assertGreaterThan(3 * $euro->width(), $numeric->width(), '&#8364; is seven glyphs, not the euro sign');
    }

    public function testTextMixingAmpersandsAndSupplementaryCharactersIsMeasuredAsItIsDrawn(): void
    {
        $font = $this->font(40);
        $text = "AT&T \u{1F600} \u{10300} &amp; kůň";

        $tight = $this->factory->text($text, $font, Color::black());
        $padded = $this->factory->text($text, $font, Color::black(), padding: 24);

        self::assertSame($this->driver()->name(), $tight->driverName());
        self::assertTextImageIsTight($tight, $padded, 24);
    }

    // Left to the renderers, "\n" stacked lines 1.05 em apart on GD and by the font's metrics on ImageMagick.
    /** @return iterable<string, array{Angle, int, int}> */
    public static function lineDirections(): iterable
    {
        yield 'upright' => [Angle::clockwise(0), 0, 48];
        yield 'rotated 90 clockwise' => [Angle::clockwise(90), -48, 0];
    }

    #[DataProvider('lineDirections')]
    public function testLinesSeparatedByNewlineAreDrawnOnePitchApartExactlyAsSeparateCalls(Angle $angle, int $stepX, int $stepY): void
    {
        $font = $this->font(40);
        $lines = ['Hg', 'Second', 'x'];

        $joined = $this->drawOnWhite(300, 300, static fn(Canvas $c) => $c->text(implode("\n", $lines), new Point(100, 100), $font, Color::black(), $angle));
        $separate = $this->drawOnWhite(300, 300, static function (Canvas $c) use ($lines, $font, $angle, $stepX, $stepY): Canvas {
            foreach ($lines as $i => $line) {
                $c = $c->text($line, new Point(100 + $i * $stepX, 100 + $i * $stepY), $font, Color::black(), $angle);
            }

            return $c;
        });

        self::assertGreaterThan(1000, self::inkCount($joined), 'three lines were drawn');
        self::assertSame($separate->pixels()->bytes, $joined->pixels()->bytes, 'pixel-identical to placing the lines 1.2 em apart by hand');
    }

    public function testLinesSeparatedByNewlineNeitherOverlapNorDriftApart(): void
    {
        $font = $this->font(40);

        $image = $this->drawOnWhite(200, 200, static fn(Canvas $c) => $c->text("Hg\nHg", new Point(10, 60), $font, Color::black()));

        // Cap height ~29 px above and the descender ~9 px below each baseline leave ~10 px of white between the lines at 48 px.
        $bands = self::inkBands($image);
        self::assertCount(2, $bands, 'two separate bands of ink rows');
        self::assertEqualsWithDelta(48, $bands[1][0] - $bands[0][0], 1, 'the second line starts one pitch lower');
        self::assertGreaterThanOrEqual(5, $bands[1][0] - $bands[0][1] - 1, 'white gap between the descender of g and the next H');
        self::assertLessThanOrEqual(15, $bands[1][0] - $bands[0][1] - 1);
    }

    public function testMultiLineTextImageIsTightAndAsTallAsItsLinesAtThePitch(): void
    {
        $font = $this->font(40);
        $text = "Hg\nHg\nHg";

        $tight = $this->factory->text($text, $font, Color::black());
        $padded = $this->factory->text($text, $font, Color::black(), padding: 24);
        $single = $this->factory->text('Hg', $font, Color::black());

        self::assertSame($this->driver()->name(), $tight->driverName());
        self::assertTextImageIsTight($tight, $padded, 24);
        self::assertEqualsWithDelta($single->height() + 2 * 48, $tight->height(), 1, 'two more pitches than a single line');
        self::assertEqualsWithDelta($single->width(), $tight->width(), 1);
    }

    public function testABlankLineKeepsItsRoomInAMultiLineTextImage(): void
    {
        $font = $this->font(40);

        $twoLines = $this->factory->text("Hg\nHg", $font, Color::black());
        $withBlank = $this->factory->text("Hg\n\nHg", $font, Color::black());

        self::assertSame($twoLines->height() + 48, $withBlank->height());
        self::assertCount(2, self::inkBands($withBlank));
    }

    /** @return iterable<string, array{string, float}> */
    public static function tightTextCases(): iterable
    {
        // The worst case of the clipping finding: a monospace line whose last glyph was cut off at 64 px.
        yield 'DejaVu Sans Mono 64 px' => ['DejaVuSansMono.ttf', 64.0];
        yield 'DejaVu Sans 24 px' => ['DejaVuSans.ttf', 24.0];
        yield 'DejaVu Serif Bold 160 px' => ['DejaVuSerif-Bold.ttf', 160.0];
    }

    #[DataProvider('tightTextCases')]
    public function testTextImageKeepsEveryInkPixelWithoutPadding(string $file, float $size): void
    {
        $path = dirname(self::FONT) . '/' . $file;
        if (!is_file($path)) {
            self::markTestSkipped($file . ' is not installed (Debian package fonts-dejavu-core).');
        }
        $font = new Font($path, $size);

        $tight = $this->factory->text(self::PANGRAM, $font, Color::black());
        $padded = $this->factory->text(self::PANGRAM, $font, Color::black(), padding: 24);

        self::assertSame($this->driver()->name(), $tight->driverName());
        self::assertTextImageIsTight($tight, $padded, 24);
    }

    private static function inkCount(Image $image): int
    {
        $pixels = $image->pixels();
        $count = 0;
        for ($offset = 0, $end = strlen($pixels->bytes); $offset < $end; $offset += 4) {
            if (ord($pixels->bytes[$offset]) < 128) {
                ++$count;
            }
        }

        return $count;
    }

    /** @return list<array{int, int}> first and last row of every run of rows holding mostly opaque pixels darker than mid-grey */
    private static function inkBands(Image $image): array
    {
        $pixels = $image->pixels();
        $width = $pixels->dimensions->width;
        $bands = [];
        $open = null;
        for ($y = 0; $y < $pixels->dimensions->height; ++$y) {
            $ink = false;
            for ($x = 0; $x < $width && !$ink; ++$x) {
                $offset = ($y * $width + $x) * 4;
                $ink = ord($pixels->bytes[$offset]) < 128 && ord($pixels->bytes[$offset + 3]) >= 128;
            }
            if ($ink) {
                $open ??= $y;
            } elseif ($open !== null) {
                $bands[] = [$open, $y - 1];
                $open = null;
            }
        }
        if ($open !== null) {
            $bands[] = [$open, $pixels->dimensions->height - 1];
        }

        return $bands;
    }
}
