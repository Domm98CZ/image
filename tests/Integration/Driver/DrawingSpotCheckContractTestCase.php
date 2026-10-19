<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\Stroke;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Tests\Support\InkAssertions;
use PHPUnit\Framework\TestCase;

// Minimal cross-driver drawing sanity subset, run on every driver; DrawingContractTestCase adds the
// full, exhaustive suite on top and is only run against the reference (Imagick) driver - see DEC-010.
abstract class DrawingSpotCheckContractTestCase extends TestCase
{
    use InkAssertions;

    protected const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

    protected ImageFactory $factory;

    abstract protected function driver(): DriverInterface;

    protected function setUp(): void
    {
        $driver = $this->driver();
        $this->factory = new ImageFactory(Configuration::default()->withForcedDriver($driver->name()), new DriverRegistry([$driver]));
    }

    public function testFilledRectangleCoversExactlyItsArea(): void
    {
        $image = $this->drawOnWhite(50, 40, static fn(Canvas $c) => $c->rectangle(new Rectangle(new Point(10, 10), new Dimensions(20, 10)), Color::black()));

        self::assertSame([10, 10, 29, 19], self::inkBox($image));
    }

    public function testEllipseSpansItsDiameters(): void
    {
        $image = $this->drawOnWhite(50, 40, static fn(Canvas $c) => $c->ellipse(new Point(25, 20), new Dimensions(20, 10), Color::black()));

        $box = self::inkBox($image);
        self::assertEqualsWithDelta([15, 15, 35, 25], $box, 1);
    }

    public function testLineConnectsItsEndpoints(): void
    {
        $image = $this->drawOnWhite(40, 40, static fn(Canvas $c) => $c->line(new Point(5, 5), new Point(34, 34), new Stroke(Color::black(), 3)));

        self::assertLessThan(100, $image->colorAt(new Point(20, 20))->red);
        self::assertSame(255, $image->colorAt(new Point(30, 5))->red);
        self::assertEqualsWithDelta([4, 4, 35, 35], self::inkBox($image), 2);
    }

    public function testATranslucentFillIsBlendedOnceWhateverStrokeWasDrawnBeforeIt(): void
    {
        $fill = Color::rgb(66, 133, 244)->withOpacity(0.16);
        $square = [new Point(30, 10), new Point(50, 10), new Point(50, 30), new Point(30, 30)];

        $image = $this->drawOnWhite(60, 50, static fn(Canvas $c) => $c
            ->line(new Point(0, 45), new Point(59, 45), new Stroke(Color::black(), 6))
            ->rectangle(new Rectangle(new Point(5, 10), new Dimensions(20, 20)), $fill)
            ->polygon($square, $fill));

        foreach ([new Point(15, 20), new Point(40, 20)] as $inside) {
            $blended = $image->colorAt($inside);
            self::assertEqualsWithDelta([225, 235, 253], [$blended->red, $blended->green, $blended->blue], 2);
        }
    }

    public function testTextSitsOnItsBaselineAtThePixelSize(): void
    {
        $font = $this->font(40);

        $image = $this->drawOnWhite(300, 80, static fn(Canvas $c) => $c->text('Hxg', new Point(10, 60), $font, Color::black()));

        // Measured with FreeType through both drivers: cap height above the baseline, descender below it.
        self::assertEqualsWithDelta([14, 30, 85, 68], self::inkBox($image), 3);
    }

    public function testTextIsRenderedLiterallyWithoutImageMagickEscapes(): void
    {
        $font = $this->font(20);
        foreach (['%w', '@/etc/hostname', '%[pixel:p{0,0}]'] as $text) {
            $image = $this->drawOnWhite(400, 40, static fn(Canvas $c) => $c->text($text, new Point(5, 30), $font, Color::black()));
            $box = imagettfbbox(15.0, 0, self::FONT, $text);
            self::assertNotFalse($box);

            [$left, , $right] = self::inkBox($image);
            self::assertEqualsWithDelta($box[2] - $box[0], $right - $left, 4, $text);
        }
    }

    // U+4E38 is absent from DejaVu: FreeType alone would paint its .notdef box on GD and ImageMagick nothing at all.
    public function testAGlyphTheFontLacksIsDrawnAsTheReplacementCharacter(): void
    {
        $font = $this->font(40);
        $draw = static fn(string $text): callable => static fn(Canvas $c): Canvas => $c->text($text, new Point(10, 60), $font, Color::black());

        $missing = $this->drawOnWhite(200, 80, $draw("a\u{4E38}b"));
        $replacement = $this->drawOnWhite(200, 80, $draw("a\u{FFFD}b"));
        $plain = $this->drawOnWhite(200, 80, $draw('ab'));

        self::assertSame($replacement->pixels()->bytes, $missing->pixels()->bytes, 'pixel-identical to drawing U+FFFD');
        self::assertGreaterThan(self::inkBox($plain)[2] + 10, self::inkBox($missing)[2], 'the placeholder takes visible room between a and b');

        $tightMissing = $this->factory->text("\u{4E38}", $font, Color::black());
        $tightReplacement = $this->factory->text("\u{FFFD}", $font, Color::black());
        self::assertSame($tightReplacement->pixels()->bytes, $tightMissing->pixels()->bytes, 'measured and rendered as U+FFFD');
        self::assertGreaterThan(10, $tightMissing->width());
    }

    protected function font(float $size): Font
    {
        if (!is_file(self::FONT)) {
            self::markTestSkipped('DejaVu Sans is not installed (Debian package fonts-dejavu-core).');
        }

        return new Font(self::FONT, $size);
    }

    /** @param callable(Canvas): Canvas $draw */
    protected function drawOnWhite(int $width, int $height, callable $draw): Image
    {
        return $this->factory->create(new Dimensions($width, $height), Color::white())->draw($draw(...))->toImage();
    }

    /** @return array{int, int, int, int} left, top, right, bottom of pixels darker than mid-grey */
    protected static function inkBox(Image $image): array
    {
        $pixels = $image->pixels();
        [$left, $top, $right, $bottom] = [PHP_INT_MAX, PHP_INT_MAX, -1, -1];
        for ($y = 0; $y < $pixels->dimensions->height; ++$y) {
            for ($x = 0; $x < $pixels->dimensions->width; ++$x) {
                if (ord($pixels->bytes[($y * $pixels->dimensions->width + $x) * 4]) < 128) {
                    $left = min($left, $x);
                    $top = min($top, $y);
                    $right = max($right, $x);
                    $bottom = max($bottom, $y);
                }
            }
        }

        return [$left, $top, $right, $bottom];
    }
}
