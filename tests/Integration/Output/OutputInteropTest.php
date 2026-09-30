<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Output;

use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Format\Output\GifOutput;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Tests\Support\GdFixtures;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// The fixtures are rendered with raw GD; the library itself would run on Imagick alone.
#[RequiresPhpExtension('gd')]
final class OutputInteropTest extends TestCase
{
    public function testAPsr7RequestBodyRoundTripsIntoAPsr7Response(): void
    {
        $psr17 = new Psr17Factory();
        $upload = $psr17->createStream(GdFixtures::quadrantsPng(64, 32));
        $upload->seek(10);

        $response = (new ImageFactory())->openStream($upload)->fitInside(new Dimensions(32, 32))->toResponse($psr17, $psr17, new WebpOutput(lossless: true));

        self::assertSame('image/webp', $response->getHeaderLine('Content-Type'));
        self::assertSame((string) $response->getBody()->getSize(), $response->getHeaderLine('Content-Length'));
        $roundTrip = (new ImageFactory())->openStream($response->getBody())->toImage();
        self::assertEquals(new Dimensions(32, 16), $roundTrip->dimensions);
        self::assertSame(255, $roundTrip->colorAt(new Point(2, 2))->red);
    }

    public function testDataUrisDecodeBackToTheSameImage(): void
    {
        $factory = new ImageFactory();

        $uri = $factory->openBytes(GdFixtures::quadrantsPng(8, 8))->toDataUri(new PngOutput());

        self::assertStringStartsWith('data:image/png;base64,', $uri);
        $bytes = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);
        self::assertIsString($bytes);
        self::assertEquals(new Dimensions(8, 8), $factory->openBytes($bytes)->toImage()->dimensions);
    }

    public function testSaveWritesEveryFormatByExtension(): void
    {
        $directory = sys_get_temp_dir() . '/image-save-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $factory = new ImageFactory();
        $builder = $factory->openBytes(GdFixtures::quadrantsPng(40, 20));

        try {
            foreach (['jpg' => JpegOutput::class, 'png' => PngOutput::class, 'gif' => GifOutput::class, 'webp' => WebpOutput::class, 'avif' => AvifOutput::class] as $extension => $output) {
                $path = sprintf('%s/out.%s', $directory, $extension);
                $encoded = $builder->save($path);

                self::assertSame((new $output())->format(), $encoded->format);
                self::assertSame($encoded->bytes, file_get_contents($path));
                self::assertEquals(new Dimensions(40, 20), $factory->open($path)->toImage()->dimensions);
            }
            self::assertCount(5, glob($directory . '/*') ?: []);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function testAnimationsSaveAndStreamToo(): void
    {
        $psr17 = new Psr17Factory();
        $factory = new ImageFactory();
        $red = $factory->create(new Dimensions(6, 6), new Color(255, 0, 0))->toImage();
        $blue = $factory->create(new Dimensions(6, 6), new Color(0, 0, 255))->toImage();
        $animation = $factory->animation([new Frame($red, 100), new Frame($blue, 100)]);

        $stream = $animation->toStream($psr17, new GifOutput());

        self::assertSame(2, $factory->openAnimationStream($stream)->toAnimation()->frameCount());
    }
}
