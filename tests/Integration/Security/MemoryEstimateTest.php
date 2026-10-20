<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Security;

use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Security\LimitType;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// Each test lowers memory_limit for itself; an estimate that is too low ends in a fatal error, not a failure.
#[RequiresPhpExtension('gd')]
#[RunTestsInSeparateProcesses]
final class MemoryEstimateTest extends TestCase
{
    public function testAnOpaque25MegapixelPngIsRefusedUnder256MegabytesInsteadOfExhaustingMemory(): void
    {
        $png = self::opaquePng(5000, 5000);
        ini_set('memory_limit', '256M');

        try {
            (new ImageFactory(new Configuration(forcedDriver: DriverName::Gd)))->openBytes($png)->encode(new PngOutput());
            self::fail('Expected LimitExceededException.');
        } catch (LimitExceededException $exception) {
            self::assertSame(LimitType::Memory, $exception->violation->limit);
        }
        self::assertLessThan(100_000, strlen($png));
    }

    public function testA25MegapixelJpegRotatedByExifStillOpensWhenTheMemoryFits(): void
    {
        $jpeg = ImageBytes::injectExifIntoJpeg(self::jpeg(6250, 4000), ImageBytes::exifTiff(6));
        ini_set('memory_limit', '384M');

        $encoded = (new ImageFactory(new Configuration(forcedDriver: DriverName::Gd)))
            ->openBytes($jpeg)
            ->autoOrient()
            ->encode(new JpegOutput(quality: 90));

        self::assertEquals(new Dimensions(4000, 6250), (new HeaderProbe())->probe(new BinaryString($encoded->bytes))->canvas);
    }

    private static function opaquePng(int $width, int $height): string
    {
        $deflate = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        self::assertNotFalse($deflate);
        $row = str_repeat("\0", 1 + 3 * $width);
        $data = '';
        for ($y = 0; $y < $height; ++$y) {
            $data .= deflate_add($deflate, $row, ZLIB_NO_FLUSH);
        }
        $data .= deflate_add($deflate, '', ZLIB_FINISH);

        return "\x89PNG\r\n\x1A\n" . ImageBytes::pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            . ImageBytes::pngChunk('IDAT', $data) . ImageBytes::pngChunk('IEND', '');
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private static function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height - 1, (int) imagecolorallocate($image, 51, 102, 153));
        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }
}
