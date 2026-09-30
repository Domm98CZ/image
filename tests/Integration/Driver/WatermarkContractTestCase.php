<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Blend\BlendMode;
use Domm98CZ\Image\Blend\Compositor;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Position;
use Domm98CZ\Image\Tests\Support\ColorAssertions;
use Domm98CZ\Image\Watermark\Payload;
use Domm98CZ\Image\Watermark\SecretKey;
use Domm98CZ\Image\Watermark\Steganography\LsbReader;
use Domm98CZ\Image\Watermark\Steganography\LsbWatermark;
use Domm98CZ\Image\Watermark\VisibleWatermark;
use Domm98CZ\Image\Watermark\WatermarkStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

// The full, exhaustive contract - only run against the reference (Imagick) driver, see DEC-010.
// GD gets the spot-check subset in WatermarkSpotCheckContractTestCase (parent of this class too).
// The photo-like plasma fixture is rendered by ImageMagick, so the steganography test needs ext-imagick on any driver.
abstract class WatermarkContractTestCase extends WatermarkSpotCheckContractTestCase
{
    use ColorAssertions;

    public function testVisibleWatermarkScalesRelativeToTheImage(): void
    {
        $logo = $this->factory->create(new Dimensions(10, 5), Color::fromHex('#0000ff'))->toImage();

        $image = $this->factory->create(new Dimensions(200, 100), Color::white())
            ->watermark(new VisibleWatermark($logo, Position::anchored(Anchor::TopLeft), relativeWidth: 0.5))
            ->toImage();

        self::assertColorNear([0, 0, 255], $image->colorAt(new Point(95, 45)));
        self::assertColorNear([255, 255, 255], $image->colorAt(new Point(105, 45)));
        self::assertColorNear([255, 255, 255], $image->colorAt(new Point(95, 55)));
    }

    /** @return iterable<string, array{BlendMode}> */
    public static function modes(): iterable
    {
        foreach (BlendMode::cases() as $mode) {
            yield $mode->name => [$mode];
        }
    }

    #[DataProvider('modes')]
    public function testBlendModesFollowTheW3cFormulas(BlendMode $mode): void
    {
        $overlay = $this->factory->create(new Dimensions(10, 10), new Color(60, 180, 240, 128))->toImage();
        $backdrop = new Color(200, 100, 50);

        $image = $this->factory->create(new Dimensions(10, 10), $backdrop)->watermark(new VisibleWatermark($overlay, Position::at(Point::origin()), 0.8, $mode))->toImage();

        $expected = (new Compositor())->blend(PixelBuffer::filled(new Dimensions(1, 1), $backdrop), PixelBuffer::filled(new Dimensions(1, 1), new Color(60, 180, 240, 128)), $mode, 0.8);
        $color = $expected->colorAt(Point::origin());
        self::assertColorNear([$color->red, $color->green, $color->blue], $image->colorAt(new Point(5, 5)), 4);
    }

    /** @return iterable<string, array{PngOutput|WebpOutput}> */
    public static function losslessOutputs(): iterable
    {
        yield 'png' => [new PngOutput()];
        yield 'lossless webp' => [new WebpOutput(lossless: true)];
    }

    #[RequiresPhpExtension('imagick')]
    #[DataProvider('losslessOutputs')]
    public function testLsbSurvivesLosslessOutput(PngOutput|WebpOutput $output): void
    {
        $key = new SecretKey(str_repeat('L', 32));

        $encoded = $this->photo()->watermark(new LsbWatermark(Payload::text('lsb-owner-1'), $key))->encode($output);
        $reading = (new LsbReader())->read($this->factory->openBytes($encoded->bytes)->toImage(), $key);

        self::assertSame(WatermarkStatus::Authentic, $reading->status);
        self::assertSame('lsb-owner-1', $reading->payload?->bytes);
    }
}
