<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Color;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\Profile\RgbMatrixProfile;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\Imagick\ImagickDriver;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Metadata\ColorProfileReader;
use Imagick;
use ImagickPixel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('gd')]
#[RequiresPhpExtension('imagick')]
final class ColorManagementTest extends TestCase
{
    // rgb(128, 64, 200) read as linear light, re-encoded with the sRGB transfer curve.
    private const CONVERTED = [188, 137, 229];
    private const RAW = [128, 64, 200];

    /** @return iterable<string, array{string}> */
    public static function formats(): iterable
    {
        yield 'png' => ['png'];
        yield 'jpeg' => ['jpeg'];
        yield 'webp' => ['webp'];
    }

    #[DataProvider('formats')]
    public function testAWideGamutInputIsDecodedByImagickAndConvertedToSrgb(string $format): void
    {
        $image = (new ImageFactory())->openBytes(self::tagged($format, RgbMatrixProfile::linearSrgbPrimaries()->bytes()))->toImage();

        self::assertSame(DriverName::Imagick, $image->driver()->name());
        self::assertColor(self::CONVERTED, $image->colorAt(new Point(3, 3)));
    }

    #[DataProvider('formats')]
    public function testGdAloneKeepsTheNumbersBecauseItCannotHonourProfiles(string $format): void
    {
        $image = (new ImageFactory(Configuration::default()->withForcedDriver(DriverName::Gd)))->openBytes(self::tagged($format, RgbMatrixProfile::linearSrgbPrimaries()->bytes()))->toImage();

        self::assertColor(self::RAW, $image->colorAt(new Point(3, 3)));
    }

    public function testAnSrgbProfileNeedsNoConversionAndStaysOnTheCheapestDriver(): void
    {
        $bytes = self::tagged('png', RgbMatrixProfile::srgb()->bytes());

        $imagick = (new ImageFactory(Configuration::default()->withForcedDriver(DriverName::Imagick)))->openBytes($bytes)->toImage();

        self::assertSame(DriverName::Gd, (new ImageFactory())->openBytes($bytes)->toImage()->driver()->name());
        self::assertColor(self::RAW, $imagick->colorAt(new Point(3, 3)));
    }

    public function testConvertedOutputIsUntaggedSrgb(): void
    {
        $bytes = self::tagged('png', RgbMatrixProfile::linearSrgbPrimaries()->bytes());

        $encoded = (new ImageFactory())->openBytes($bytes)->encode(new PngOutput());

        self::assertNull((new ColorProfileReader())->read(new BinaryString($encoded->bytes), FormatName::Png));
        self::assertColor(self::CONVERTED, (new ImageFactory())->openBytes($encoded->bytes)->toImage()->colorAt(new Point(3, 3)));
    }

    public function testAProfileLcmsRejectsIsIgnoredAndTheImageStillOpens(): void
    {
        $bogus = substr_replace(RgbMatrixProfile::linearSrgbPrimaries()->bytes(), str_repeat("\xAB", 40), 400, 40);

        $image = (new ImageFactory())->openBytes(self::tagged('jpeg', $bogus))->toImage();

        self::assertColor(self::RAW, $image->colorAt(new Point(3, 3)));
    }

    public function testTheInstalledImageMagickHasColorManagement(): void
    {
        self::assertSame(Support::Native, (new ImagickDriver())->colorManagement());
    }

    private static function tagged(string $format, string $profile): string
    {
        $image = new Imagick();
        $image->newImage(8, 8, new ImagickPixel(sprintf('rgb(%d,%d,%d)', ...self::RAW)));
        $image->setImageProfile('icc', $profile);
        $image->setImageFormat($format);
        $image->setOption('png:color-type', '2');
        $image->setOption('webp:lossless', 'true');
        $image->setImageCompressionQuality(100);

        return $image->getImageBlob();
    }

    /** @param array{int, int, int} $expected */
    private static function assertColor(array $expected, Color $actual): void
    {
        self::assertEqualsWithDelta($expected, [$actual->red, $actual->green, $actual->blue], 4);
    }
}
