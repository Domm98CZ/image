<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Optimization;

use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Optimization\MetadataStripper;
use Domm98CZ\Image\Tests\Support\ColorAssertions;
use Domm98CZ\Image\Tests\Support\GdFixtures;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// Real encoder output with the review's payloads injected: stripped, it has to decode to the same pixels.
#[RequiresPhpExtension('gd')]
final class MetadataStripperTest extends TestCase
{
    use ColorAssertions;

    private const TRAILER = '<html><script>SECRET-TRAILER</script></html>';

    private ImageFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ImageFactory(new Configuration(forcedDriver: DriverName::Gd));
    }

    public function testBaselineAndProgressiveJpegDecodeAfterStripping(): void
    {
        foreach ([false, true] as $progressive) {
            $jpeg = $this->factory->openBytes(GdFixtures::quadrantsPng(40, 20))->encode(new JpegOutput(quality: 95, progressive: $progressive))->bytes;
            $lastScan = (int) strrpos($jpeg, "\xFF\xDA");
            $hostile = ImageBytes::injectExifIntoJpeg(substr($jpeg, 0, $lastScan), ImageBytes::exifTiff(6))
                . self::segment(0xFE, 'SECRET-BETWEEN-SCANS') . self::segment(0xE1, "Exif\0\0" . ImageBytes::exifTiff(1))
                . substr($jpeg, $lastScan) . self::TRAILER;

            $stripped = (new MetadataStripper())->optimize(new EncodedImage($hostile, FormatName::Jpeg))->bytes;

            self::assertStringNotContainsString('SECRET', $stripped);
            self::assertStringNotContainsString('Exif', $stripped);
            self::assertStringEndsWith("\xFF\xD9", $stripped);
            $this->assertQuadrants($this->factory->openBytes($stripped)->toImage());
        }
    }

    public function testPngDecodesAfterStripping(): void
    {
        $png = $this->factory->openBytes(GdFixtures::quadrantsPng(40, 20))->encode(new PngOutput())->bytes;
        $headerEnd = 8 + 25;
        $hostile = substr($png, 0, $headerEnd)
            . ImageBytes::pngChunk('tEXt', "Comment\0SECRET-TEXT") . ImageBytes::pngChunk('prVt', 'SECRET-PRIVATE-ANCILLARY')
            . substr($png, $headerEnd) . '<?php system($_GET["c"]); ?> SECRET-TRAILER';

        $stripped = (new MetadataStripper())->optimize(new EncodedImage($hostile, FormatName::Png))->bytes;

        self::assertStringNotContainsString('SECRET', $stripped);
        self::assertSame(ImageBytes::pngChunk('IEND', ''), substr($stripped, -12));
        $this->assertQuadrants($this->factory->openBytes($stripped)->toImage());
    }

    private function assertQuadrants(Image $image): void
    {
        self::assertEquals(new Dimensions(40, 20), $image->dimensions);
        self::assertColorNear(GdFixtures::TOP_LEFT, $image->colorAt(new Point(5, 5)), 40);
        self::assertColorNear(GdFixtures::BOTTOM_RIGHT, $image->colorAt(new Point(34, 14)), 40);
    }

    private static function segment(int $marker, string $payload): string
    {
        return "\xFF" . chr($marker) . pack('n', 2 + strlen($payload)) . $payload;
    }
}
