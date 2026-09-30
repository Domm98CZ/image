<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Format;

use Domm98CZ\Image\Exception\InvalidInputException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use GdImage;
use Imagick;
use ImagickPixel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Throwable;

// Cross-checks the pure-PHP probes against what real libgd / ImageMagick encoders produce.
#[RequiresPhpExtension('gd')]
#[RequiresPhpExtension('imagick')]
final class HeaderProbeAgainstEncodersTest extends TestCase
{
    /** @return iterable<string, array{callable(): string, FormatName, int, int, int, bool|null}> */
    public static function encodedImages(): iterable
    {
        yield 'gd jpeg' => [static fn(): string => self::gdEncode(self::gdCanvas(321, 123, false), 'imagejpeg', 85), FormatName::Jpeg, 321, 123, 1, false];
        yield 'gd progressive jpeg' => [static function (): string {
            $image = self::gdCanvas(200, 100, false);
            imageinterlace($image, true);

            return self::gdEncode($image, 'imagejpeg', 85);
        }, FormatName::Jpeg, 200, 100, 1, false];
        yield 'gd png rgba' => [static fn(): string => self::gdEncode(self::gdCanvas(77, 55, true), 'imagepng'), FormatName::Png, 77, 55, 1, true];
        yield 'gd png palette with transparent color' => [static function (): string {
            $image = imagecreate(40, 30);
            self::assertInstanceOf(GdImage::class, $image);
            $transparent = imagecolorallocate($image, 1, 2, 3);
            self::assertIsInt($transparent);
            imagecolortransparent($image, $transparent);

            return self::gdEncode($image, 'imagepng');
        }, FormatName::Png, 40, 30, 1, true];
        yield 'gd png rgb (alpha not saved)' => [static fn(): string => self::gdEncode(self::gdCanvas(77, 55, false), 'imagepng'), FormatName::Png, 77, 55, 1, false];
        yield 'imagick png opaque palette without tRNS' => [static function (): string {
            $image = new Imagick();
            $image->newImage(48, 32, new ImagickPixel('rgba(200, 100, 50, 1.0)'));
            $image->setImageFormat('png');

            return $image->getImageBlob();
        }, FormatName::Png, 48, 32, 1, false];
        yield 'gd gif' => [static fn(): string => self::gdEncode(self::gdCanvas(64, 48, false), 'imagegif'), FormatName::Gif, 64, 48, 1, false];
        yield 'gd webp lossy' => [static fn(): string => self::gdEncode(self::gdCanvas(250, 150, false), 'imagewebp', 80), FormatName::Webp, 250, 150, 1, false];
        yield 'gd webp lossless' => [static fn(): string => self::gdEncode(self::gdCanvas(90, 60, false), 'imagewebp', IMG_WEBP_LOSSLESS), FormatName::Webp, 90, 60, 1, false];
        yield 'gd webp with alpha' => [static fn(): string => self::gdEncode(self::gdCanvas(90, 60, true), 'imagewebp', 80), FormatName::Webp, 90, 60, 1, true];
        yield 'gd avif' => [static fn(): string => self::gdEncode(self::gdCanvas(96, 64, false), 'imageavif', 60), FormatName::Avif, 96, 64, 1, false];
        yield 'imagick animated gif' => [static fn(): string => self::imagickAnimation('gif', 120, 80, 4), FormatName::Gif, 120, 80, 4, false];
        yield 'imagick animated webp' => [static fn(): string => self::imagickAnimation('webp', 120, 80, 3), FormatName::Webp, 120, 80, 3, false];
        yield 'imagick avif with alpha' => [static function (): string {
            $image = new Imagick();
            $image->newImage(64, 32, new ImagickPixel('rgba(255, 0, 0, 0.5)'));
            $image->setImageFormat('avif');

            return $image->getImageBlob();
        }, FormatName::Avif, 64, 32, 1, null];
    }

    /** @param callable(): string $encode */
    #[DataProvider('encodedImages')]
    public function testProbeMatchesEncoder(callable $encode, FormatName $format, int $width, int $height, int $frames, ?bool $alpha): void
    {
        $bytes = $encode();
        $header = (new HeaderProbe())->probe(new BinaryString($bytes));
        // Older ImageMagick (Debian bookworm) silently drops alpha when writing AVIF, so ask the decoder.
        $alpha ??= self::imagickSeesAlpha($bytes);

        self::assertSame($format, $header->format);
        self::assertSame([$width, $height], [$header->canvas->width, $header->canvas->height]);
        self::assertSame($frames, $header->frameCount);
        self::assertSame($alpha, $header->hasAlpha);
    }

    /** @param callable(): string $encode */
    #[DataProvider('encodedImages')]
    public function testTruncatedRealFilesFailCleanly(callable $encode): void
    {
        $bytes = $encode();
        $step = max(1, intdiv(strlen($bytes), 400));
        for ($length = 0; $length < strlen($bytes); $length += $step) {
            try {
                (new HeaderProbe())->probe(new BinaryString(substr($bytes, 0, $length)));
            } catch (InvalidInputException) {
            } catch (Throwable $unexpected) {
                self::fail(sprintf('%s at prefix %d: %s', $unexpected::class, $length, $unexpected->getMessage()));
            }
        }
        $this->addToAssertionCount(1);
    }

    private static function imagickSeesAlpha(string $bytes): bool
    {
        $image = new Imagick();
        $image->readImageBlob($bytes);

        return $image->getImageAlphaChannel();
    }

    private static function gdCanvas(int $width, int $height, bool $alpha): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertInstanceOf(GdImage::class, $image);
        imagealphablending($image, false);
        imagesavealpha($image, $alpha);
        $color = imagecolorallocatealpha($image, 200, 100, 50, $alpha ? 64 : 0);
        self::assertIsInt($color);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $color);

        return $image;
    }

    private static function gdEncode(GdImage $image, string $encoder, ?int $quality = null): string
    {
        ob_start();
        $quality === null ? $encoder($image) : $encoder($image, null, $quality);

        return (string) ob_get_clean();
    }

    private static function imagickAnimation(string $format, int $width, int $height, int $frames): string
    {
        $animation = new Imagick();
        for ($frame = 0; $frame < $frames; ++$frame) {
            $animation->newImage($width, $height, new ImagickPixel(sprintf('rgb(%d, 0, 0)', $frame * 50)));
            $animation->setImageFormat($format);
            $animation->setImageDelay(10);
        }

        return $animation->getImagesBlob();
    }
}
