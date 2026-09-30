<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\Operation\Interpolation;
use Domm98CZ\Image\Operation\Pad;
use Domm98CZ\Image\Operation\Trim;
use Domm98CZ\Image\Tests\Support\ColorAssertions;
use Domm98CZ\Image\Tests\Support\GdFixtures;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use Imagick;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

// The full, exhaustive contract - only run against the reference (Imagick) driver, see DEC-010.
// GD gets the spot-check subset in DriverSpotCheckContractTestCase (parent of this class too).
abstract class DriverContractTestCase extends DriverSpotCheckContractTestCase
{
    use ColorAssertions;

    public function testPadPlacesTheImageInsideTheNewCanvas(): void
    {
        $image = $this->factory->create(new Dimensions(2, 2), Color::fromHex('#123456'))
            ->pad(1, 2, 3, 4, Color::white())
            ->toImage();

        self::assertEquals(new Dimensions(6, 8), $image->dimensions);
        self::assertTrue($image->colorAt(new Point(0, 0))->equals(Color::white()));
        self::assertTrue($image->colorAt(new Point(1, 2))->equals(Color::fromHex('#123456')));
        self::assertTrue($image->colorAt(new Point(5, 7))->equals(Color::white()));
        self::assertSame(Support::Native, $this->driver()->support(Pad::class));
    }

    public function testTrimRemovesTheUniformOuterBorder(): void
    {
        $image = $this->factory->create(new Dimensions(6, 6), Color::white())
            ->applyPixels(static function (PixelBuffer $pixels): PixelBuffer {
                $bytes = $pixels->bytes;
                foreach ([2, 3] as $y) {
                    foreach ([2, 3] as $x) {
                        $offset = ($y * $pixels->dimensions->width + $x) * PixelBuffer::BYTES_PER_PIXEL;
                        $bytes[$offset] = chr(0);
                        $bytes[$offset + 1] = chr(0);
                        $bytes[$offset + 2] = chr(0);
                    }
                }

                return $pixels->withBytes($bytes);
            })
            ->trim()
            ->toImage();

        self::assertEquals(new Dimensions(2, 2), $image->dimensions);
        self::assertTrue($image->colorAt(Point::origin())->equals(Color::black()));
        self::assertSame($this->driver()->name() === DriverName::Gd ? Support::PhpFallback : Support::Native, $this->driver()->support(Trim::class));
    }

    public function testPasteAndPixelOperationsKeepExpectedPixels(): void
    {
        $overlay = $this->factory->create(new Dimensions(1, 1), Color::fromHex('#00ff00'))->toImage();
        $mask = $this->factory->create(new Dimensions(2, 2), new Color(0, 0, 0, 128))->toImage();
        $image = $this->factory->create(new Dimensions(2, 2), Color::fromHex('#ff0000'))
            ->paste($overlay, new Point(1, 0))
            ->opacity(0.5)
            ->mask($mask)
            ->toImage();

        self::assertSame([255, 0, 0], [$image->colorAt(new Point(0, 0))->red, $image->colorAt(new Point(0, 0))->green, $image->colorAt(new Point(0, 0))->blue]);
        self::assertSame([0, 255, 0], [$image->colorAt(new Point(1, 0))->red, $image->colorAt(new Point(1, 0))->green, $image->colorAt(new Point(1, 0))->blue]);
        self::assertEqualsWithDelta(64, $image->colorAt(new Point(0, 0))->alpha, 2);
    }

    public function testNearestInterpolationKeepsTheQuadrantColours(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(200, 100))->resize(new Dimensions(50, 30), Interpolation::Nearest)->toImage();

        $this->assertQuadrants($image, GdFixtures::TOP_LEFT, GdFixtures::TOP_RIGHT, GdFixtures::BOTTOM_LEFT, GdFixtures::BOTTOM_RIGHT);
        self::assertSame(Support::Native, $this->driver()->support(\Domm98CZ\Image\Operation\Resize::class));
    }

    public function testHasAlphaReflectsRealPixels(): void
    {
        $opaque = $this->factory->create(new Dimensions(4, 4), Color::fromHex('#123456'))->toImage();
        $translucent = $this->factory->create(new Dimensions(4, 4), new Color(0x12, 0x34, 0x56, 128))->toImage();

        self::assertFalse($opaque->hasAlpha());
        self::assertTrue($opaque->isOpaque());
        self::assertTrue($translucent->hasAlpha());
        self::assertFalse($translucent->isOpaque());
    }

    public function testAverageColorOfQuadrantsIsTheirMean(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(2, 2))->toImage();
        $average = $image->averageColor();

        $expected = array_map(
            static fn(int $channel): int => (int) round(array_sum(array_column([GdFixtures::TOP_LEFT, GdFixtures::TOP_RIGHT, GdFixtures::BOTTOM_LEFT, GdFixtures::BOTTOM_RIGHT], $channel)) / 4),
            [0, 1, 2],
        );
        self::assertEqualsWithDelta($expected[0], $average->red, 2);
        self::assertEqualsWithDelta($expected[1], $average->green, 2);
        self::assertEqualsWithDelta($expected[2], $average->blue, 2);
    }

    public function testDominantColorOfASolidImageIsThatColor(): void
    {
        $image = $this->factory->create(new Dimensions(8, 8), Color::fromHex('#ff8000'))->toImage();

        self::assertTrue($image->dominantColor()->equals(Color::fromHex('#ff8000')));
        self::assertCount(1, $image->palette(3), 'a solid-color image cannot be split into more than one cluster');
    }

    public function testHistogramCountsEveryPixel(): void
    {
        $image = $this->factory->create(new Dimensions(3, 5), Color::fromHex('#ff0000'))->toImage();
        $histogram = $image->histogram();

        self::assertSame(15, $histogram->red[255]);
        self::assertSame(15, $histogram->green[0]);
        self::assertSame(15, $histogram->blue[0]);
        self::assertSame(15, array_sum($histogram->red));
    }

    public function testBlurhashRoundTripsThroughARealDriver(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(40, 40))->toImage();

        $hash = $image->blurhash();

        self::assertMatchesRegularExpression('/^[0-9A-Za-z#$%*+,\-.:;=?@\[\]^_{|}~]{28}$/', $hash);
    }

    public function testPerceptualHashDetectsNearDuplicatesAndDistinctImages(): void
    {
        $original = $this->factory->openBytes(GdFixtures::quadrantsPng(64, 64))->toImage();
        $identical = $this->factory->openBytes(GdFixtures::quadrantsPng(64, 64))->toImage();
        $recompressed = $this->factory->openBytes($original->encode(new JpegOutput(quality: 90))->bytes)->toImage();
        $unrelated = $this->factory->openBytes(GdFixtures::verticalSplitPng(64, 64))->toImage();

        $originalHash = $original->perceptualHash();
        self::assertSame(16, strlen($originalHash));
        self::assertSame(0, \Domm98CZ\Image\Analysis\PerceptualHash::hammingDistance($originalHash, $identical->perceptualHash()));
        $recompressedDistance = \Domm98CZ\Image\Analysis\PerceptualHash::hammingDistance($originalHash, $recompressed->perceptualHash());
        $unrelatedDistance = \Domm98CZ\Image\Analysis\PerceptualHash::hammingDistance($originalHash, $unrelated->perceptualHash());
        self::assertLessThanOrEqual(8, $recompressedDistance, 'JPEG recompression at quality 90 should barely move the hash');
        self::assertGreaterThan($recompressedDistance, $unrelatedDistance, 'a structurally unrelated image must be farther than a recompressed near-duplicate');
    }

    public function testArbitraryRotationFillsCornersWithBackground(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(200, 100))->rotate(Angle::clockwise(30), Color::white())->toImage();

        self::assertEquals(new Dimensions(223, 187), $image->dimensions);
        self::assertColorNear([255, 255, 255], $image->colorAt(new Point(0, 0)));
    }

    public function testJpegFlattensTransparencyOntoBackground(): void
    {
        $encoded = $this->factory->openBytes(GdFixtures::solidPng(16, 16, 0, 0, 0, 127))->encode(new JpegOutput(background: Color::fromHex('#ff8800')));

        self::assertColorNear([255, 136, 0], $this->factory->openBytes($encoded->bytes)->toImage()->colorAt(new Point(8, 8)), 6);
    }

    public function testEncodingDoesNotMutateTheImage(): void
    {
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(16, 16, transparentBottomRight: true))->toImage();

        $image->encode(new JpegOutput(progressive: true));

        self::assertTrue($image->colorAt(new Point(12, 12))->isFullyTransparent());
        self::assertSame(
            $this->factory->from($image)->encode(new PngOutput())->bytes,
            $this->factory->from($image)->encode(new PngOutput())->bytes,
        );
    }

    public function testOpaquePngIsWrittenWithoutAnAlphaChannelAndDecodesToTheSamePixels(): void
    {
        // The fixture itself is RGBA with every alpha at 255; odd dimensions keep any row/chunk arithmetic honest.
        $image = $this->factory->openBytes(GdFixtures::quadrantsPng(131, 67))->toImage();

        $encoded = $image->encode(new PngOutput());
        $header = (new HeaderProbe())->probe(new BinaryString($encoded->bytes));
        $reopened = $this->factory->openBytes($encoded->bytes)->toImage();

        self::assertFalse($header->hasAlpha);
        self::assertSame(255, $reopened->colorAt(new Point(130, 66))->alpha);
        self::assertColorNear(GdFixtures::BOTTOM_RIGHT, $reopened->colorAt(new Point(130, 66)), 0);
        self::assertSame($image->pixels()->bytes, $reopened->pixels()->bytes);
    }

    public function testASingleTranslucentPixelKeepsThePngAlphaChannel(): void
    {
        $opaque = $this->noise(64, 48);
        // Only the very last pixel is translucent, so the whole image has to be scanned to notice it.
        $translucent = $this->factory->from($opaque)->applyPixels(static function (PixelBuffer $pixels): PixelBuffer {
            $bytes = $pixels->bytes;
            $bytes[strlen($bytes) - 1] = chr(128);

            return $pixels->withBytes($bytes);
        })->toImage();

        $opaquePng = $opaque->encode(new PngOutput());
        $translucentPng = $translucent->encode(new PngOutput());

        self::assertFalse((new HeaderProbe())->probe(new BinaryString($opaquePng->bytes))->hasAlpha);
        self::assertTrue((new HeaderProbe())->probe(new BinaryString($translucentPng->bytes))->hasAlpha);
        self::assertLessThan(strlen($translucentPng->bytes), strlen($opaquePng->bytes));
        self::assertEqualsWithDelta(128, $this->factory->openBytes($translucentPng->bytes)->toImage()->colorAt(new Point(63, 47))->alpha, 2);
    }

    public function testPngOutputCanKeepTheAlphaChannelOfAnOpaqueImage(): void
    {
        $opaque = $this->noise(32, 24);

        $forced = $opaque->encode(new PngOutput(keepAlphaChannel: true));
        $default = $opaque->encode(new PngOutput());

        self::assertTrue((new HeaderProbe())->probe(new BinaryString($forced->bytes))->hasAlpha);
        self::assertGreaterThan(strlen($default->bytes), strlen($forced->bytes));
        self::assertSame($opaque->pixels()->bytes, $this->factory->openBytes($forced->bytes)->toImage()->pixels()->bytes);
    }

    // Deterministic per-pixel noise: incompressible, so RGB rows are measurably smaller than RGBA rows.
    private function noise(int $width, int $height): Image
    {
        return $this->factory->create(new Dimensions($width, $height), Color::white())->applyPixels(static function (PixelBuffer $pixels): PixelBuffer {
            mt_srand(1234);
            $bytes = '';
            for ($i = 0; $i < $pixels->dimensions->pixelCount(); ++$i) {
                $bytes .= chr(mt_rand(0, 255)) . chr(mt_rand(0, 255)) . chr(mt_rand(0, 255)) . "\xFF";
            }

            return $pixels->withBytes($bytes);
        })->toImage();
    }

    /** @return iterable<string, array{int}> */
    public static function orientations(): iterable
    {
        foreach (range(1, 8) as $orientation) {
            yield 'orientation ' . $orientation => [$orientation];
        }
    }

    #[RequiresPhpExtension('imagick')]
    #[DataProvider('orientations')]
    public function testAutoOrientMatchesImagickReference(int $orientation): void
    {
        $stored = $this->factory->openBytes(GdFixtures::quadrantsPng(120, 60))->encode(new JpegOutput(quality: 95));
        $withExif = ImageBytes::injectExifIntoJpeg($stored->bytes, ImageBytes::exifTiff($orientation));

        $ours = $this->factory->openBytes($withExif)->autoOrient()->toImage();

        $reference = new Imagick();
        $reference->readImageBlob($withExif);
        $reference->autoOrient();
        self::assertSame([$reference->getImageWidth(), $reference->getImageHeight()], [$ours->width(), $ours->height()]);
        foreach ([[0.25, 0.25], [0.75, 0.25], [0.25, 0.75], [0.75, 0.75]] as [$fx, $fy]) {
            $x = (int) ($ours->width() * $fx);
            $y = (int) ($ours->height() * $fy);
            $pixel = $reference->getImagePixelColor($x, $y)->getColor();
            self::assertColorNear([(int) $pixel['r'], (int) $pixel['g'], (int) $pixel['b']], $ours->colorAt(new Point($x, $y)), 40);
        }
    }
}
