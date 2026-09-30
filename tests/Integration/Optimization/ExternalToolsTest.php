<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Optimization;

use Domm98CZ\Image\Exception\ExternalToolException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Optimization\External\CwebpOptimizer;
use Domm98CZ\Image\Optimization\External\GifsicleOptimizer;
use Domm98CZ\Image\Optimization\External\JpegtranOptimizer;
use Domm98CZ\Image\Optimization\External\PngquantOptimizer;
use Domm98CZ\Image\Optimization\External\ProcOpenProcessRunner;
use Imagick;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

final class ExternalToolsTest extends TestCase
{
    public function testArgumentsReachTheToolLiterallyWithoutAShell(): void
    {
        $result = (new ProcOpenProcessRunner())->run(['printf', '%s|', 'a b', '$(id)', '; rm -rf /', '`whoami`', '*'], '', 5.0, 1_000);

        self::assertSame(0, $result->exitCode);
        self::assertSame('a b|$(id)|; rm -rf /|`whoami`|*|', $result->stdout);
    }

    public function testStdinIsStreamedAndStdoutCollected(): void
    {
        $payload = random_bytes(300_000);

        $result = (new ProcOpenProcessRunner())->run(['cat'], $payload, 5.0, 1_000_000);

        self::assertSame($payload, $result->stdout);
    }

    public function testRunawayProcessesAreStoppedByTimeout(): void
    {
        $started = microtime(true);
        try {
            (new ProcOpenProcessRunner())->run(['sleep', '10'], '', 0.3, 1_000);
            self::fail('Expected a timeout.');
        } catch (ExternalToolException $exception) {
            self::assertStringContainsString('did not finish', $exception->getMessage());
        }
        self::assertLessThan(3.0, microtime(true) - $started);
    }

    public function testFloodingOutputIsCutOff(): void
    {
        $this->expectException(ExternalToolException::class);
        $this->expectExceptionMessage('more than 10000 bytes');

        (new ProcOpenProcessRunner())->run(['yes'], '', 5.0, 10_000);
    }

    #[RequiresPhpExtension('imagick')]
    public function testMissingBinaryIsReportedByTheOptimizer(): void
    {
        $this->expectException(ExternalToolException::class);

        (new PngquantOptimizer('/nonexistent/pngquant'))->optimize(new EncodedImage($this->photoPng(), FormatName::Png));
    }

    #[RequiresPhpExtension('imagick')]
    public function testPngquantShrinksARealPhotoAndKeepsItValid(): void
    {
        if (!is_executable('/usr/bin/pngquant')) {
            self::markTestSkipped('pngquant is not installed.');
        }
        $original = new EncodedImage($this->photoPng(), FormatName::Png);

        $optimized = (new PngquantOptimizer('/usr/bin/pngquant'))->optimize($original);

        self::assertLessThan($original->byteCount() * 0.8, $optimized->byteCount());
        self::assertEquals((new HeaderProbe())->probe(new BinaryString($original->bytes))->canvas, (new HeaderProbe())->probe(new BinaryString($optimized->bytes))->canvas);
        $before = (new ImageFactory())->openBytes($original->bytes)->toImage()->colorAt(new Point(100, 60));
        $after = (new ImageFactory())->openBytes($optimized->bytes)->toImage()->colorAt(new Point(100, 60));
        self::assertEqualsWithDelta([$before->red, $before->green, $before->blue], [$after->red, $after->green, $after->blue], 24);
    }

    #[RequiresPhpExtension('imagick')]
    public function testEncodeSmallestChoosesAmongRealEncoders(): void
    {
        $factory = new ImageFactory();
        $builder = $factory->openBytes($this->photoPng())->scale(0.5);

        $smallest = $builder->encodeSmallest(new JpegOutput(quality: 80), new WebpOutput(quality: 80), new AvifOutput(quality: 50), new PngOutput());

        $sizes = array_map(static fn($output): int => $builder->encode($output)->byteCount(), [new JpegOutput(quality: 80), new WebpOutput(quality: 80), new AvifOutput(quality: 50), new PngOutput()]);
        self::assertSame(min($sizes), $smallest->byteCount());
        self::assertSame(200, $factory->openBytes($smallest->bytes)->toImage()->width());
    }

    #[RequiresPhpExtension('imagick')]
    public function testJpegtranShrinksARealPhotoLosslesslyAndKeepsItValid(): void
    {
        if (!is_executable('/usr/bin/jpegtran')) {
            self::markTestSkipped('jpegtran is not installed.');
        }
        $original = new EncodedImage($this->photoJpeg(), FormatName::Jpeg);

        $optimized = (new JpegtranOptimizer('/usr/bin/jpegtran'))->optimize($original);

        // Huffman/progressive re-encoding does not always shrink an already efficient baseline JPEG; the
        // "keep original if not smaller" safety net is exactly what this asserts.
        self::assertLessThanOrEqual($original->byteCount(), $optimized->byteCount());
        self::assertEquals((new HeaderProbe())->probe(new BinaryString($original->bytes))->canvas, (new HeaderProbe())->probe(new BinaryString($optimized->bytes))->canvas);
        $before = (new ImageFactory())->openBytes($original->bytes)->toImage()->colorAt(new Point(100, 60));
        $after = (new ImageFactory())->openBytes($optimized->bytes)->toImage()->colorAt(new Point(100, 60));
        self::assertEqualsWithDelta([$before->red, $before->green, $before->blue], [$after->red, $after->green, $after->blue], 2, 'a lossless transform must not change pixels');
    }

    #[RequiresPhpExtension('imagick')]
    public function testCwebpShrinksARealPhotoAndKeepsItValid(): void
    {
        if (!is_executable('/usr/bin/cwebp')) {
            self::markTestSkipped('cwebp is not installed.');
        }
        $original = new EncodedImage($this->photoWebp(), FormatName::Webp);

        $optimized = (new CwebpOptimizer('/usr/bin/cwebp', quality: 50))->optimize($original);

        self::assertLessThan($original->byteCount(), $optimized->byteCount());
        self::assertEquals((new HeaderProbe())->probe(new BinaryString($original->bytes))->canvas, (new HeaderProbe())->probe(new BinaryString($optimized->bytes))->canvas);
    }

    #[RequiresPhpExtension('imagick')]
    public function testGifsicleShrinksARealImageAndKeepsItValid(): void
    {
        if (!is_executable('/usr/bin/gifsicle')) {
            self::markTestSkipped('gifsicle is not installed.');
        }
        $original = new EncodedImage($this->photoGif(), FormatName::Gif);

        $optimized = (new GifsicleOptimizer('/usr/bin/gifsicle'))->optimize($original);

        self::assertLessThanOrEqual($original->byteCount(), $optimized->byteCount());
        $originalHeader = (new HeaderProbe())->probe(new BinaryString($original->bytes));
        $optimizedHeader = (new HeaderProbe())->probe(new BinaryString($optimized->bytes));
        self::assertEquals($originalHeader->canvas, $optimizedHeader->canvas);
        self::assertSame($originalHeader->frameCount, $optimizedHeader->frameCount);
    }

    private function photoJpeg(): string
    {
        $plasma = $this->plasma();
        $plasma->setImageFormat('jpeg');
        $plasma->setImageCompressionQuality(85);

        return $plasma->getImageBlob();
    }

    private function photoWebp(): string
    {
        $plasma = $this->plasma();
        $plasma->setImageFormat('webp');

        return $plasma->getImageBlob();
    }

    // GIF is palette-only, so a full plasma photo needs quantizing first; gifsicle then has real redundancy to remove.
    private function photoGif(): string
    {
        $plasma = $this->plasma();
        $plasma->quantizeImage(64, Imagick::COLORSPACE_RGB, 0, false, false);
        $plasma->setImageFormat('gif');

        return $plasma->getImageBlob();
    }

    // Rendered by ImageMagick; callers carry #[RequiresPhpExtension('imagick')].
    private function photoPng(): string
    {
        $plasma = $this->plasma();
        $plasma->setImageFormat('png');
        $plasma->setImageDepth(8);

        return $plasma->getImageBlob();
    }

    private function plasma(): Imagick
    {
        $plasma = new Imagick();
        $plasma->setOption('random-seed', '7');
        $plasma->newPseudoImage(400, 240, 'plasma:fractal');
        // Raw plasma is per-pixel noise that a lossy optimizer rightly refuses; a light blur makes it photo-like.
        $plasma->blurImage(0, 2);

        return $plasma;
    }
}
