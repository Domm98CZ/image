<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Imagick;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Driver\Imagick\ImagickDriver;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Header\ImageHeader;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Security\ValidatedInput;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use Imagick;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('imagick')]
final class ImagickDriverTest extends TestCase
{
    protected function tearDown(): void
    {
        new ImagickDriver(Limits::default());
    }

    public function testAppliesLimitsAsImageMagickResourceLimits(): void
    {
        new ImagickDriver(Limits::default()->withMaxWidth(1234)->withMaxHeight(567)->withMaxFrames(89));

        self::assertSame(1234, (int) Imagick::getResourceLimit(Imagick::RESOURCETYPE_WIDTH));
        self::assertSame(567, (int) Imagick::getResourceLimit(Imagick::RESOURCETYPE_HEIGHT));
        self::assertSame(89, (int) Imagick::getResourceLimit(Imagick::RESOURCETYPE_LISTLENGTH));
    }

    public function testRejectsBytesThatImageMagickDecodesAsAnotherFormat(): void
    {
        $gif = ImageBytes::gif(1, 1, [[1, 1]]);
        $claimedPng = new ValidatedInput($gif, new ImageHeader(FormatName::Png, new Dimensions(1, 1)));

        $this->expectException(CorruptedImageException::class);
        $this->expectExceptionMessage('decoded the data as GIF');

        (new ImagickDriver())->decode($claimedPng);
    }

    #[RequiresPhpExtension('gd')]
    public function testAvifDecodingSupportMatchesWhatTheDecoderActuallyDoes(): void
    {
        $gd = imagecreatetruecolor(8, 8);
        self::assertNotFalse($gd);
        imagefill($gd, 0, 0, (int) imagecolorallocate($gd, 0, 0, 255));
        ob_start();
        imageavif($gd, null, 90);
        $blueAvif = (string) ob_get_clean();

        $driver = new ImagickDriver();
        $decoded = $driver->decode(new ValidatedInput($blueAvif, new ImageHeader(FormatName::Avif, new Dimensions(8, 8))));
        $color = $driver->colorAt($decoded, new Point(4, 4));
        $correct = $color->blue > 200 && $color->red < 60 && $color->green < 60;

        self::assertSame($correct ? Support::Native : Support::Degraded, $driver->decodingSupport(FormatName::Avif));
    }

    // ImageMagick 7 trims a canvas with nothing on it to 1x1 at page offset -1,-1; that must not leak out as a text box.
    public function testWhitespaceOnlyTextMeasuresAsItsAdvanceBox(): void
    {
        $path = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        if (!is_file($path)) {
            self::markTestSkipped('DejaVu Sans is not installed (Debian package fonts-dejavu-core).');
        }

        $box = (new ImagickDriver())->measureText('   ', new Font($path, 48));

        self::assertSame(0, $box->left());
        self::assertLessThan(0, $box->top());
        self::assertGreaterThan(30, $box->dimensions->width);
        self::assertGreaterThan(30, $box->dimensions->height);
    }

    public function testAvifEncodingSupportMatchesWhatTheEncoderActuallyDoes(): void
    {
        $driver = new ImagickDriver();
        $support = $driver->encodingSupport(new AvifOutput());
        if ($support === Support::None) {
            self::markTestSkipped('This ImageMagick build cannot write AVIF.');
        }

        $encoded = $driver->encode($driver->create(new Dimensions(4, 4), new Color(255, 0, 0, 128)), new AvifOutput());
        $keepsAlpha = (new HeaderProbe())->probe(new BinaryString($encoded->bytes))->hasAlpha;

        self::assertSame($keepsAlpha ? Support::Native : Support::Degraded, $support);
    }
}
