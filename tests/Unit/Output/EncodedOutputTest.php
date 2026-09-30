<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Output;

use Domm98CZ\Image\Exception\InvalidPathException;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\GifOutput;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputFormats;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Tests\Fake\FakeDriver;
use Domm98CZ\Image\Tests\Fake\FakeImageFactory;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use Domm98CZ\Image\Tests\Support\InMemoryStream;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class EncodedOutputTest extends TestCase
{
    public function testDataUriCarriesTheMimeTypeAndBase64Bytes(): void
    {
        self::assertSame('data:image/png;base64,AP8K', (new EncodedImage("\x00\xFF\n", FormatName::Png))->toDataUri());
        self::assertSame('jpg', (new EncodedImage('x', FormatName::Jpeg))->fileExtension());
    }

    public function testStreamsFromTheCallersFactoryAreRewoundToTheStart(): void
    {
        $factory = new class implements StreamFactoryInterface {
            public function createStream(string $content = ''): StreamInterface
            {
                return InMemoryStream::positionedAtEnd($content);
            }

            public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
            {
                throw new \LogicException('not used');
            }

            /** @param resource $resource */
            public function createStreamFromResource($resource): StreamInterface
            {
                throw new \LogicException('not used');
            }
        };

        $stream = (new EncodedImage('image-bytes', FormatName::Webp))->toStream($factory);

        self::assertSame('image-bytes', $stream->getContents());
    }

    public function testResponsesDeclareTypeLengthAndNoSniffing(): void
    {
        $psr17 = new Psr17Factory();

        $response = (new EncodedImage('abc', FormatName::Avif))->toResponse($psr17, $psr17, 201);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('image/avif', $response->getHeaderLine('Content-Type'));
        self::assertSame('3', $response->getHeaderLine('Content-Length'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('abc', (string) $response->getBody());
    }

    public function testBuilderTerminalsEncodeExactlyOnceThroughTheDriver(): void
    {
        $psr17 = new Psr17Factory();
        $driver = new FakeDriver();
        $builder = FakeImageFactory::with($driver)->openBytes(ImageBytes::png(4, 3))->resize(new Dimensions(2, 2));

        $stream = $builder->toStream($psr17, new WebpOutput());
        self::assertSame('fake-webp-2x2', $stream->getContents());
        self::assertSame(['decode png 4x3 -> #1', 'apply #1 resize 2x2', 'encode #1 webp'], $driver->calls);

        self::assertSame('data:image/jpeg;base64,' . base64_encode('fake-jpeg-2x2'), $builder->toDataUri(new JpegOutput()));
        self::assertSame('image/jpeg', $builder->toResponse($psr17, $psr17, new JpegOutput())->getHeaderLine('Content-Type'));
    }

    public function testAnimationsHaveTheSameTerminals(): void
    {
        $psr17 = new Psr17Factory();
        $animation = FakeImageFactory::with(new FakeDriver())->openAnimationBytes(ImageBytes::animatedGif(4, 4, [[0, 0, 4, 4, 1, 10, null, false], [0, 0, 4, 4, 1, 10, null, false]]));

        $response = $animation->toResponse($psr17, $psr17, new GifOutput());

        self::assertSame('image/gif', $response->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('data:image/gif;base64,R0lGOD', $animation->toDataUri(new GifOutput()));
        self::assertStringStartsWith('GIF89a', $animation->toStream($psr17, new GifOutput())->getContents());
    }

    /** @return iterable<string, array{string, ?OutputFormatInterface, FormatName}> */
    public static function acceptedPaths(): iterable
    {
        yield 'inferred, upper case' => ['/out/photo.JPEG', null, FormatName::Jpeg];
        yield 'inferred jpe' => ['/out/photo.jpe', null, FormatName::Jpeg];
        yield 'explicit matching' => ['/out/photo.webp', new WebpOutput(quality: 50), FormatName::Webp];
        yield 'explicit, unknown extension' => ['/out/photo.bin', new PngOutput(), FormatName::Png];
        yield 'explicit, no extension' => ['/out/photo', new JpegOutput(), FormatName::Jpeg];
    }

    #[DataProvider('acceptedPaths')]
    public function testOutputFormatFollowsThePath(string $path, ?OutputFormatInterface $output, FormatName $expected): void
    {
        $chosen = OutputFormats::forPath($path, $output);

        self::assertSame($expected, $chosen->format());
        if ($output !== null) {
            self::assertSame($output, $chosen);
        }
    }

    public function testAKnownExtensionThatContradictsTheOutputIsRefused(): void
    {
        $this->expectException(InvalidPathException::class);
        $this->expectExceptionMessage('use ".jpg"');

        OutputFormats::forPath('/out/photo.png', new JpegOutput());
    }

    public function testSaveChecksThePathBeforeAnyWork(): void
    {
        $driver = new FakeDriver();
        $builder = FakeImageFactory::with($driver)->openBytes(ImageBytes::png(4, 3));

        try {
            $builder->save(sys_get_temp_dir() . '/never-written.gif', new PngOutput());
            self::fail('Expected InvalidPathException.');
        } catch (InvalidPathException) {
            self::assertSame([], $driver->calls);
        }
    }
}
