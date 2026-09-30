<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Watermark;

use Domm98CZ\Image\Blend\BlendMode;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Position;
use Domm98CZ\Image\Tests\Fake\FakeDriver;
use Domm98CZ\Image\Tests\Fake\FakeImageFactory;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use Domm98CZ\Image\Watermark\MetadataWatermark;
use Domm98CZ\Image\Watermark\MetadataWatermarkReader;
use Domm98CZ\Image\Watermark\Payload;
use Domm98CZ\Image\Watermark\SecretKey;
use Domm98CZ\Image\Watermark\Steganography\DctWatermark;
use Domm98CZ\Image\Watermark\Steganography\LsbWatermark;
use Domm98CZ\Image\Watermark\VisibleWatermark;
use Domm98CZ\Image\Watermark\WatermarkStatus;
use PHPUnit\Framework\TestCase;

final class WatermarkBuilderTest extends TestCase
{
    public function testVisibleWatermarkBecomesOneCompositeAtItsResolvedPlacement(): void
    {
        $driver = new FakeDriver();
        $factory = FakeImageFactory::with($driver);
        $logo = $factory->create(new Dimensions(100, 50))->toImage();
        $driver->calls = [];

        $factory->openBytes(ImageBytes::png(800, 600))
            ->watermark(new VisibleWatermark($logo, Position::inset(Anchor::BottomRight, 10), 0.5, BlendMode::Multiply, relativeWidth: 0.25))
            ->toImage();

        self::assertSame(['decode png 800x600 -> #2', 'apply #2 composite 200x100+590+490 opacity 0.5 multiply'], $driver->calls);
    }

    public function testSteganographyAlwaysRunsAfterEveryOtherStep(): void
    {
        $driver = new FakeDriver();

        FakeImageFactory::with($driver)->openBytes(ImageBytes::png(800, 800))
            ->watermark(new DctWatermark(Payload::text('late')))
            ->grayscale()
            ->scale(0.5)
            ->toImage();

        self::assertSame(['decode png 800x800 -> #1', 'apply #1 grayscale', 'apply #1 resize 400x400', 'read #1 400x400+0+0', 'write #1 400x400+0+0'], $driver->calls);
    }

    public function testLsbIsRefusedForLossyOutputBeforeAnyWork(): void
    {
        $driver = new FakeDriver();
        $builder = FakeImageFactory::with($driver)->openBytes(ImageBytes::png(40, 40))->watermark(new LsbWatermark(Payload::text('x')));

        foreach ([new JpegOutput(), new WebpOutput(lossless: false)] as $lossy) {
            try {
                $builder->encode($lossy);
                self::fail('Expected InvalidWatermarkException for ' . $lossy->format()->label());
            } catch (InvalidWatermarkException) {
            }
        }
        self::assertSame([], $driver->calls);
    }

    public function testMetadataWatermarkIsAppliedAfterEncoding(): void
    {
        $key = new SecretKey(str_repeat('m', 32));
        $builder = FakeImageFactory::with(new FakeDriver())->openBytes(ImageBytes::png(8, 8))->watermark(new MetadataWatermark(Payload::text('meta'), $key));

        $encoded = $builder->encode(new PngOutput());
        $reading = (new MetadataWatermarkReader())->read($encoded, $key);

        self::assertSame(FormatName::Png, $encoded->format);
        self::assertSame(WatermarkStatus::Authentic, $reading->status);
        self::assertSame(WatermarkStatus::Unverified, (new MetadataWatermarkReader())->read($encoded)->status);
        self::assertSame(WatermarkStatus::Tampered, (new MetadataWatermarkReader())->read($encoded, new SecretKey(str_repeat('z', 32)))->status);

        $this->expectException(InvalidWatermarkException::class);
        $builder->toImage();
    }

    public function testUnkeyedMetadataWatermarkIsIntact(): void
    {
        $encoded = FakeImageFactory::with(new FakeDriver())->create(new Dimensions(4, 4), Color::white())
            ->watermark(new MetadataWatermark(Payload::text('open'), creator: 'Me', rights: '(c) Me'))
            ->encode(new PngOutput());

        self::assertSame(WatermarkStatus::Intact, (new MetadataWatermarkReader())->read($encoded)->status);
    }
}
