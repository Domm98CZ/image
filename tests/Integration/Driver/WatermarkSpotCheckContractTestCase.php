<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Driver\Gd\GdDriver;
use Domm98CZ\Image\Driver\Imagick\ImagickDriver;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Position;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\ImageBuilder;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Tests\Support\ColorAssertions;
use Domm98CZ\Image\Watermark\MetadataWatermark;
use Domm98CZ\Image\Watermark\MetadataWatermarkReader;
use Domm98CZ\Image\Watermark\Payload;
use Domm98CZ\Image\Watermark\SecretKey;
use Domm98CZ\Image\Watermark\Steganography\DctReader;
use Domm98CZ\Image\Watermark\Steganography\DctWatermark;
use Domm98CZ\Image\Watermark\VisibleWatermark;
use Domm98CZ\Image\Watermark\WatermarkStatus;
use Imagick;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// Minimal cross-driver watermark sanity subset, run on every driver; WatermarkContractTestCase adds
// the full, exhaustive suite on top and is only run against the reference (Imagick) driver - see DEC-010.
abstract class WatermarkSpotCheckContractTestCase extends TestCase
{
    use ColorAssertions;

    protected const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    protected ImageFactory $factory;

    abstract protected function driver(): DriverInterface;

    protected function setUp(): void
    {
        $driver = $this->driver();
        $this->factory = new ImageFactory(Configuration::default()->withForcedDriver($driver->name()), new DriverRegistry([$driver]));
    }

    public function testVisibleWatermarkWithOpacityIsPlacedAtItsInset(): void
    {
        $logo = $this->factory->create(new Dimensions(20, 10), Color::fromHex('#ff0000'))->toImage();

        $image = $this->factory->create(new Dimensions(100, 60), Color::white())
            ->watermark(new VisibleWatermark($logo, Position::inset(Anchor::BottomRight, 5), 0.5))
            ->toImage();

        self::assertColorNear([255, 128, 128], $image->colorAt(new Point(85, 50)));
        self::assertColorNear([255, 255, 255], $image->colorAt(new Point(74, 50)));
        self::assertColorNear([255, 255, 255], $image->colorAt(new Point(85, 56)));
    }

    #[RequiresPhpExtension('gd')]
    #[RequiresPhpExtension('imagick')]
    public function testOverlayHeldByTheOtherDriverIsAccepted(): void
    {
        $other = $this->driver() instanceof GdDriver ? new ImagickDriver() : new GdDriver();
        $logo = (new ImageFactory(Configuration::default()->withForcedDriver($other->name()), new DriverRegistry([$other])))
            ->create(new Dimensions(10, 10), Color::fromHex('#00ff00'))->toImage();

        $image = $this->factory->create(new Dimensions(30, 30), Color::white())->watermark(new VisibleWatermark($logo, Position::anchored(Anchor::Center)))->toImage();

        self::assertColorNear([0, 255, 0], $image->colorAt(new Point(15, 15)));
    }

    public function testTextWatermarkIsAnchoredByItsMeasuredSize(): void
    {
        if (!is_file(self::FONT)) {
            self::markTestSkipped('DejaVu Sans Bold is not installed.');
        }
        $text = $this->factory->text('© ACME', new Font(self::FONT, 24), Color::black(), padding: 2);

        $image = $this->factory->create(new Dimensions(300, 120), Color::white())->watermark(new VisibleWatermark($text, Position::inset(Anchor::BottomRight, 6)))->toImage();

        $ink = self::inkBox($image);
        self::assertEqualsWithDelta(300 - 6 - 2, $ink[2], 4, 'right edge of the text');
        self::assertEqualsWithDelta(120 - 6 - 2, $ink[3], 4, 'bottom edge of the text');
        self::assertGreaterThan(150, $ink[0], 'text stays in the right half');
    }

    #[RequiresPhpExtension('imagick')]
    public function testDctSurvivesJpegRecompression(): void
    {
        $key = new SecretKey(str_repeat('D', 32));

        $encoded = $this->photo()->watermark(new DctWatermark(Payload::text('dct-owner-7'), $key))->encode(new JpegOutput(quality: 60));
        $reading = (new DctReader())->read($this->factory->openBytes($encoded->bytes)->toImage(), $key);

        self::assertSame(WatermarkStatus::Authentic, $reading->status);
        self::assertSame('dct-owner-7', $reading->payload?->bytes);
    }

    #[RequiresPhpExtension('imagick')]
    public function testAllThreeWatermarksTogetherInOneCdnStyleJpeg(): void
    {
        $key = new SecretKey(str_repeat('C', 32));
        $logo = $this->factory->create(new Dimensions(40, 20), Color::rgba(255, 255, 255, 0.6))->toImage();

        $encoded = $this->photo()
            ->fitInside(new Dimensions(600, 600))
            ->watermark(new DctWatermark(Payload::text('cust-77'), $key))
            ->watermark(new VisibleWatermark($logo, Position::inset(Anchor::BottomRight, 10), 0.8))
            ->watermark(new MetadataWatermark(Payload::text('asset-9912'), $key, 'ACME Studio', '(c) 2026 ACME'))
            ->encode(new JpegOutput(quality: 80));

        $image = $this->factory->openBytes($encoded->bytes)->toImage();
        self::assertSame(WatermarkStatus::Authentic, (new DctReader())->read($image, $key)->status);
        self::assertSame(WatermarkStatus::Authentic, (new MetadataWatermarkReader())->read($encoded, $key)->status);

        $path = tempnam(sys_get_temp_dir(), 'wm');
        self::assertIsString($path);
        file_put_contents($path, $encoded->bytes);
        $exif = exif_read_data($path);
        getimagesize($path, $info);
        unlink($path);
        self::assertIsArray($exif);
        self::assertSame('ACME Studio', $exif['Artist'] ?? null);
        self::assertSame('(c) 2026 ACME', $exif['Copyright'] ?? null);
        $iptc = iptcparse(is_string($info['APP13'] ?? null) ? $info['APP13'] : '');
        self::assertIsArray($iptc);
        self::assertSame('ACME Studio', $iptc['2#080'][0] ?? null);
    }

    protected function photo(): ImageBuilder
    {
        $plasma = new Imagick();
        $plasma->setOption('random-seed', '42');
        $plasma->newPseudoImage(800, 500, 'plasma:fractal');
        $plasma->setImageFormat('png');

        return $this->factory->openBytes($plasma->getImageBlob());
    }

    /** @return array{int, int, int, int} */
    private static function inkBox(Image $image): array
    {
        $pixels = $image->pixels();
        [$left, $top, $right, $bottom] = [PHP_INT_MAX, PHP_INT_MAX, -1, -1];
        for ($y = 0; $y < $pixels->dimensions->height; ++$y) {
            for ($x = 0; $x < $pixels->dimensions->width; ++$x) {
                if (ord($pixels->bytes[($y * $pixels->dimensions->width + $x) * 4]) < 128) {
                    [$left, $top, $right, $bottom] = [min($left, $x), min($top, $y), max($right, $x), max($bottom, $y)];
                }
            }
        }

        return [$left, $top, $right, $bottom];
    }
}
