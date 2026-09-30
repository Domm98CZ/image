<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Format\Output\GifOutput;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Metadata\ExifOrientationReader;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Operation\FlipDirection;
use Domm98CZ\Image\Tests\Support\ColorAssertions;
use Domm98CZ\Image\Tests\Support\GdFixtures;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Minimal cross-driver sanity subset, run on every driver; DriverContractTestCase adds the full,
// exhaustive suite on top and is only run against the reference (Imagick) driver - see DEC-010.
abstract class DriverSpotCheckContractTestCase extends TestCase
{
    use ColorAssertions;

    protected ImageFactory $factory;

    abstract protected function driver(): DriverInterface;

    protected function setUp(): void
    {
        $driver = $this->driver();
        $this->factory = new ImageFactory(Configuration::default()->withForcedDriver($driver->name()), new DriverRegistry([$driver]));
    }

    public function testResizeKeepsQuadrants(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(200, 100))->resize(new Dimensions(50, 30))->toImage();

        self::assertEquals(new Dimensions(50, 30), $image->dimensions);
        self::assertSame($this->driver()->name(), $image->driverName());
        $this->assertQuadrants($image, GdFixtures::TOP_LEFT, GdFixtures::TOP_RIGHT, GdFixtures::BOTTOM_LEFT, GdFixtures::BOTTOM_RIGHT);
    }

    public function testCropTakesTheRequestedRegion(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(200, 100))
            ->crop(new Rectangle(new Point(100, 50), new Dimensions(100, 50)))
            ->toImage();

        self::assertEquals(new Dimensions(100, 50), $image->dimensions);
        $this->assertQuadrants($image, GdFixtures::BOTTOM_RIGHT, GdFixtures::BOTTOM_RIGHT, GdFixtures::BOTTOM_RIGHT, GdFixtures::BOTTOM_RIGHT);
    }

    public function testRotateClockwiseMovesTopLeftToTopRight(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(200, 100))->rotate(Angle::clockwise(90))->toImage();

        self::assertEquals(new Dimensions(100, 200), $image->dimensions);
        $this->assertQuadrants($image, GdFixtures::BOTTOM_LEFT, GdFixtures::TOP_LEFT, GdFixtures::BOTTOM_RIGHT, GdFixtures::TOP_RIGHT);
    }

    public function testFlips(): void
    {
        $horizontal = $this->factory->openBytes(GdFixtures::quadrantsPng(200, 100))->flip(FlipDirection::Horizontal)->toImage();
        $vertical = $this->factory->openBytes(GdFixtures::quadrantsPng(200, 100))->flip(FlipDirection::Vertical)->toImage();

        $this->assertQuadrants($horizontal, GdFixtures::TOP_RIGHT, GdFixtures::TOP_LEFT, GdFixtures::BOTTOM_RIGHT, GdFixtures::BOTTOM_LEFT);
        $this->assertQuadrants($vertical, GdFixtures::BOTTOM_LEFT, GdFixtures::BOTTOM_RIGHT, GdFixtures::TOP_LEFT, GdFixtures::TOP_RIGHT);
    }

    public function testTransparencySurvivesEveryGeometryOperation(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(200, 100, transparentBottomRight: true))
            ->resize(new Dimensions(100, 50))
            ->rotate(Angle::clockwise(180))
            ->flip(FlipDirection::Both)
            ->crop(new Rectangle(new Point(50, 25), new Dimensions(50, 25)))
            ->toImage();

        self::assertTrue($image->colorAt(new Point(25, 12))->isFullyTransparent());
    }

    public function testSemiTransparentAlphaSurvivesWithinGdPrecision(): void
    {
        $image = $this->factory->openBytes(GdFixtures::solidPng(4, 4, 10, 20, 30, 63))->flip(FlipDirection::Horizontal)->toImage();

        $color = $image->colorAt(new Point(1, 1));
        self::assertSame([10, 20, 30], [$color->red, $color->green, $color->blue]);
        self::assertEqualsWithDelta(128, $color->alpha, 2);
    }

    public function testPixelBufferMatchesColorAt(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(20, 10, transparentBottomRight: true))->toImage();
        $pixels = $image->pixels(new Rectangle(new Point(5, 2), new Dimensions(10, 6)));

        self::assertEquals(new Dimensions(10, 6), $pixels->dimensions);
        foreach ([[0, 0], [9, 0], [0, 5], [9, 5], [4, 2]] as [$x, $y]) {
            self::assertTrue(
                $pixels->colorAt(new Point($x, $y))->equals($image->colorAt(new Point($x + 5, $y + 2))),
                sprintf('pixel %d,%d', $x, $y),
            );
        }
    }

    public function testPixelWriteReadRoundTripIncludingAlpha(): void
    {
        $driver = $this->driver();
        $handle = $driver->create(new Dimensions(6, 4), Color::white());
        $tile = new PixelBuffer(new Dimensions(2, 2), "\xFF\x00\x00\xFF" . "\x00\xFF\x00\x80" . "\x00\x00\xFF\x00" . "\x10\x20\x30\xFF");

        $handle = $driver->writePixels($handle, $tile, new Point(3, 1));
        $read = $driver->readPixels($handle, new Rectangle(new Point(3, 1), new Dimensions(2, 2)));

        self::assertTrue($read->colorAt(new Point(0, 0))->equals(new Color(255, 0, 0)));
        self::assertTrue($read->colorAt(new Point(1, 1))->equals(new Color(16, 32, 48)));
        self::assertSame(0, $read->colorAt(new Point(0, 1))->alpha);
        self::assertEqualsWithDelta(128, $read->colorAt(new Point(1, 0))->alpha, 2);
        self::assertTrue($driver->colorAt($handle, new Point(0, 0))->equals(Color::white()));
    }

    public function testPixelAreaOutsideImageIsRejected(): void
    {
        $driver = $this->driver();
        $handle = $driver->create(new Dimensions(4, 4), Color::white());

        $this->expectException(InvalidOperationException::class);

        $driver->readPixels($handle, new Rectangle(new Point(2, 2), new Dimensions(3, 1)));
    }

    public function testOutputNeverCarriesAnOrientationTag(): void
    {
        $stored = $this->factory->openBytes(GdFixtures::quadrantsPng(40, 20))->encode(new JpegOutput());
        $tagged = ImageBytes::injectExifIntoJpeg($stored->bytes, ImageBytes::exifTiff(6));

        $output = $this->factory->openBytes($tagged)->encode(new JpegOutput());

        self::assertSame(Orientation::TopLeft, (new ExifOrientationReader())->read(new BinaryString($output->bytes), FormatName::Jpeg));
    }

    /** @return iterable<string, array{OutputFormatInterface}> */
    public static function outputs(): iterable
    {
        yield 'jpeg' => [new JpegOutput(quality: 80, progressive: true)];
        yield 'png' => [new PngOutput(compressionLevel: 9)];
        yield 'gif' => [new GifOutput()];
        yield 'webp lossy' => [new WebpOutput(quality: 70)];
        yield 'webp lossless' => [new WebpOutput(lossless: true)];
        yield 'avif' => [new AvifOutput(quality: 50, speed: 8)];
    }

    #[DataProvider('outputs')]
    public function testEncodesEveryFormatIntoAReadableImage(OutputFormatInterface $output): void
    {
        if (!$this->driver()->encodingSupport($output)->isSupported()) {
            self::markTestSkipped(sprintf('%s cannot encode %s in this environment.', $this->driver()->name()->value, $output->format()->label()));
        }
        $encoded = $this->factory->openBytes(GdFixtures::quadrantsPng(64, 48))->encode($output);
        $header = (new HeaderProbe())->probe(new BinaryString($encoded->bytes));
        if ($this->driver()->decodingSupport($output->format()) !== Support::Native) {
            self::markTestSkipped(sprintf('%s cannot decode %s losslessly in this environment.', $this->driver()->name()->value, $output->format()->label()));
        }
        $reopened = $this->factory->openBytes($encoded->bytes)->toImage();

        self::assertSame($output->format(), $encoded->format);
        self::assertSame($output->format(), $header->format);
        self::assertEquals(new Dimensions(64, 48), $reopened->dimensions);
        self::assertColorNear(GdFixtures::TOP_LEFT, $reopened->colorAt(new Point(8, 8)), 40);
    }

    public function testCreatedCanvasHasBackgroundColor(): void
    {
        $image = $this->factory->create(new Dimensions(3, 2), Color::fromHex('#123456'))->toImage();

        self::assertSame('#123456', $image->colorAt(new Point(2, 1))->toHex());
    }

    public function testCorruptedPixelDataIsReportedAsInvalidInput(): void
    {
        $valid = GdFixtures::quadrantsPng(64, 64);
        $idat = strpos($valid, 'IDAT');
        self::assertIsInt($idat);
        $corrupted = substr($valid, 0, $idat + 4) . str_repeat("\x00", strlen($valid) - $idat - 4);

        $this->expectException(CorruptedImageException::class);

        $this->factory->openBytes($corrupted)->toImage();
    }

    public function testSavesToDisk(): void
    {
        $path = sys_get_temp_dir() . '/gd-pipeline-' . bin2hex(random_bytes(4)) . '.webp';

        $this->factory->openBytes(GdFixtures::quadrantsPng(40, 20))->scale(0.5)->save($path);

        self::assertEquals(new Dimensions(20, 10), $this->factory->open($path)->toImage()->dimensions);
        unlink($path);
    }

    public function testHeicDecodingIsImagickOnlyAndOnlyForDecoding(): void
    {
        if ($this->driver()->name() === DriverName::Gd) {
            self::assertSame(Support::None, $this->driver()->decodingSupport(FormatName::Heic));
            $this->expectException(UnsupportedFormatException::class);
            $this->factory->openBytes(ImageBytes::heic())->toImage();

            return;
        }

        self::assertSame(Support::Native, $this->driver()->decodingSupport(FormatName::Heic));
        $image = $this->factory->openBytes(ImageBytes::heic())->toImage();
        self::assertSame(FormatName::Heic, (new HeaderProbe())->probe(new BinaryString(ImageBytes::heic()))->format);
        self::assertEquals(new Dimensions(20, 15), $image->dimensions);
        self::assertColorNear([255, 0, 0], $image->colorAt(new Point(10, 7)), 24);
    }

    /**
     * @param array{int, int, int} $topLeft
     * @param array{int, int, int} $topRight
     * @param array{int, int, int} $bottomLeft
     * @param array{int, int, int} $bottomRight
     */
    protected function assertQuadrants(Image $image, array $topLeft, array $topRight, array $bottomLeft, array $bottomRight): void
    {
        $x1 = intdiv($image->width(), 4);
        $x2 = intdiv($image->width() * 3, 4);
        $y1 = intdiv($image->height(), 4);
        $y2 = intdiv($image->height() * 3, 4);
        self::assertColorNear($topLeft, $image->colorAt(new Point($x1, $y1)));
        self::assertColorNear($topRight, $image->colorAt(new Point($x2, $y1)));
        self::assertColorNear($bottomLeft, $image->colorAt(new Point($x1, $y2)));
        self::assertColorNear($bottomRight, $image->colorAt(new Point($x2, $y2)));
    }
}
