<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\InvalidPathException;
use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Exception\UnrecognizedFormatException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Operation\Resize;
use Domm98CZ\Image\Operation\Scale;
use Domm98CZ\Image\Pipeline\CostModel;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Tests\Fake\FakeDriver;
use Domm98CZ\Image\Tests\Fake\FakeImageFactory;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use Domm98CZ\Image\Tests\Support\InMemoryStream;
use PHPUnit\Framework\TestCase;

final class ImageBuilderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/image-builder-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testFluentCallsReturnNewBuildersAndLeaveTheOriginalUntouched(): void
    {
        $original = FakeImageFactory::with(new FakeDriver())->openBytes(ImageBytes::png(10, 10));

        $resized = $original->resize(new Dimensions(5, 5));
        $scaled = $resized->scale(2.0);

        self::assertSame([], $original->operations());
        self::assertEquals([new Resize(new Dimensions(5, 5))], $resized->operations());
        self::assertEquals([new Resize(new Dimensions(5, 5)), new Scale(2.0)], $scaled->operations());
    }

    public function testNothingRunsUntilATerminalCall(): void
    {
        $driver = new FakeDriver();

        FakeImageFactory::with($driver)->openBytes(ImageBytes::png(10, 10))->resize(new Dimensions(5, 5))->scale(2.0);

        self::assertSame([], $driver->calls);
    }

    public function testInvalidInputFailsAtOpenNotAtSave(): void
    {
        $this->expectException(UnrecognizedFormatException::class);

        FakeImageFactory::with(new FakeDriver())->openBytes('definitely not an image');
    }

    public function testOpensFilesAndStreams(): void
    {
        $factory = FakeImageFactory::with(new FakeDriver());
        $path = $this->directory . '/input.png';
        file_put_contents($path, ImageBytes::png(12, 7));

        self::assertEquals(new Dimensions(12, 7), $factory->open($path)->toImage()->dimensions);
        self::assertEquals(new Dimensions(3, 4), $factory->openStream(new InMemoryStream(ImageBytes::gif(3, 4, [[3, 4]])))->toImage()->dimensions);
    }

    public function testImageExposesQueriesThroughItsDriver(): void
    {
        $image = FakeImageFactory::with(new FakeDriver(pixelColor: Color::white()))->openBytes(ImageBytes::webpLossy(30, 20))->toImage();

        self::assertSame(30, $image->width());
        self::assertSame(20, $image->height());
        self::assertSame(FormatName::Webp, $image->sourceFormat);
        self::assertSame(DriverName::Gd, $image->driverName());
        self::assertTrue($image->colorAt(new Point(0, 0))->equals(Color::white()));
    }

    public function testSaveInfersFormatFromExtensionAndWritesAtomically(): void
    {
        $path = $this->directory . '/out.JPG';

        $encoded = FakeImageFactory::with(new FakeDriver())->openBytes(ImageBytes::png(4, 3))->save($path);

        self::assertSame(FormatName::Jpeg, $encoded->format);
        self::assertSame('fake-jpeg-4x3', file_get_contents($path));
        self::assertSame([$path], glob($this->directory . '/*'));
    }

    public function testSaveWithExplicitFormatIgnoresExtension(): void
    {
        $path = $this->directory . '/out.bin';

        FakeImageFactory::with(new FakeDriver())->openBytes(ImageBytes::png(4, 3))->save($path, new JpegOutput());

        self::assertSame('fake-jpeg-4x3', file_get_contents($path));
    }

    public function testSaveRejectsUnknownExtensionWithoutExplicitFormat(): void
    {
        $this->expectException(InvalidPathException::class);

        FakeImageFactory::with(new FakeDriver())->openBytes(ImageBytes::png(4, 3))->save($this->directory . '/out.bmp');
    }

    public function testCreateRespectsLimits(): void
    {
        $factory = FakeImageFactory::configured(new Configuration(Limits::default()->withMaxPixels(99)), new FakeDriver());

        $this->expectException(LimitExceededException::class);

        $factory->create(new Dimensions(10, 10));
    }

    // The same image, filter and drivers: only the configured prices decide whether the pixel step is worth a transfer.
    public function testTheConfiguredCostModelDecidesWhereAPixelStepRuns(): void
    {
        $gd = new FakeDriver(DriverName::Gd, pixelAccess: Support::PhpFallback);
        $imagick = new FakeDriver(DriverName::Imagick);
        $filter = static fn(PixelBuffer $pixels): PixelBuffer => $pixels;
        $reference = FakeImageFactory::with($gd, $imagick);
        $calibrated = FakeImageFactory::configured(Configuration::default()->withCostModel(new CostModel(transferNanosPerPixel: 45.0)), $gd, $imagick);
        $onGd = $reference->create(new Dimensions(4, 4))->toImage();

        self::assertSame(DriverName::Gd, $onGd->driverName());
        self::assertSame(DriverName::Gd, $reference->from($onGd)->applyPixels($filter)->toImage()->driverName());
        self::assertSame(DriverName::Imagick, $calibrated->from($onGd)->applyPixels($filter)->toImage()->driverName());
    }

    public function testCreateDefaultsToTransparentBackground(): void
    {
        $driver = new FakeDriver();

        FakeImageFactory::with($driver)->create(new Dimensions(2, 2))->toImage();

        self::assertSame(['create 2x2 #00000000 -> #1'], $driver->calls);
    }
}
