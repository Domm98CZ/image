<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Driver;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\IncompatibleHandleException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\ImageHeader;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Operation\Crop;
use Domm98CZ\Image\Operation\Resize;
use Domm98CZ\Image\Security\ValidatedInput;
use Domm98CZ\Image\Tests\Fake\FakeDriver;
use Domm98CZ\Image\Tests\Fake\FakeImageHandle;
use PHPUnit\Framework\TestCase;

// The fake is the oracle of every unit test, so its own bookkeeping is pinned down here.
final class FakeDriverTest extends TestCase
{
    public function testRecordsTheConversationWithSequentialHandleIds(): void
    {
        $driver = new FakeDriver();

        $decoded = $driver->decode(new ValidatedInput('png', new ImageHeader(FormatName::Png, new Dimensions(40, 20))));
        $resized = $driver->apply($decoded, new Resize(new Dimensions(20, 10)));
        $copy = $driver->copy($resized);
        $driver->readPixels($copy, new Rectangle(new Point(2, 3), new Dimensions(4, 5)));
        $driver->writePixels($copy, PixelBuffer::filled(new Dimensions(1, 1), Color::black()), new Point(6, 7));

        self::assertSame([
            'decode png 40x20 -> #1',
            'apply #1 resize 20x10',
            'copy #1 -> #2',
            'read #2 4x5+2+3',
            'write #2 1x1+6+7',
        ], $driver->calls);
        self::assertEquals(new Dimensions(20, 10), $driver->dimensions($resized));
        self::assertEquals(new Dimensions(20, 10), $driver->dimensions($copy));
    }

    public function testRejectsHandlesOfAnotherDriver(): void
    {
        $driver = new FakeDriver(DriverName::Gd);
        $foreign = new FakeImageHandle(DriverName::Imagick, new Dimensions(1, 1), 1);

        $this->expectExceptionObject(IncompatibleHandleException::belongsTo(DriverName::Imagick, DriverName::Gd));

        $driver->dimensions($foreign);
    }

    public function testSupportMatrixFollowsTheConstructorLists(): void
    {
        $driver = new FakeDriver(
            unsupportedOperations: [Crop::class],
            undecodable: [FormatName::Avif],
            unencodable: [FormatName::Gif],
            fallbackOperations: [Resize::class],
            degradedEncoding: [FormatName::Webp],
            degradedDecoding: [FormatName::Jpeg],
            pixelAccess: Support::PhpFallback,
            colorManagement: Support::Degraded,
        );

        self::assertSame(Support::None, $driver->support(Crop::class));
        self::assertSame(Support::PhpFallback, $driver->support(Resize::class));
        self::assertSame(Support::None, $driver->decodingSupport(FormatName::Avif));
        self::assertSame(Support::Degraded, $driver->decodingSupport(FormatName::Jpeg));
        self::assertSame(Support::Native, $driver->decodingSupport(FormatName::Png));
        self::assertSame(Support::Degraded, $driver->encodingSupport(new WebpOutput()));
        self::assertSame(Support::Native, $driver->encodingSupport(new PngOutput()));
        self::assertSame(Support::PhpFallback, $driver->pixelAccess());
        self::assertSame(Support::Degraded, $driver->colorManagement());
    }

    public function testEncodesRealPngHeadersAndPaddedFakeBytesOtherwise(): void
    {
        $driver = new FakeDriver(encodedByteCounts: ['jpeg' => 64]);
        $handle = $driver->create(new Dimensions(8, 4), Color::white());

        $png = $driver->encode($handle, new PngOutput());
        $jpeg = $driver->encode($handle, new JpegOutput());

        self::assertSame(FormatName::Png, $png->format);
        self::assertStringStartsWith("\x89PNG", $png->bytes);
        self::assertSame(FormatName::Jpeg, $jpeg->format);
        self::assertSame(64, strlen($jpeg->bytes));
        self::assertStringStartsWith('fake-jpeg-8x4', $jpeg->bytes);
        self::assertSame(['create 8x4 #ffffff -> #1', 'encode #1 png', 'encode #1 jpeg'], $driver->calls);
    }

    public function testPixelReadsReturnTheConfiguredColour(): void
    {
        $driver = new FakeDriver(pixelColor: new Color(1, 2, 3));
        $handle = $driver->create(new Dimensions(2, 2), Color::white());

        self::assertSame('#010203', $driver->colorAt($handle, new Point(0, 0))->toHex());
        self::assertSame('#010203', $driver->readPixels($handle, new Rectangle(Point::origin(), new Dimensions(2, 2)))->colorAt(new Point(1, 1))->toHex());
    }
}
