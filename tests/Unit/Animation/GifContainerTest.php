<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Animation;

use Domm98CZ\Image\Animation\Gif\GifContainerReader;
use Domm98CZ\Image\Animation\Gif\GifContainerWriter;
use Domm98CZ\Image\Animation\Gif\GifDisposal;
use Domm98CZ\Image\Animation\Gif\GifFrameSource;
use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Exception\InvalidInputException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\TestCase;
use Throwable;

final class GifContainerTest extends TestCase
{
    public function testReadsFramesWithTheirControlData(): void
    {
        $gif = ImageBytes::animatedGif(40, 30, [
            [0, 0, 40, 30, 1, 10, null, false],
            [5, 6, 10, 12, 2, 25, 1, true],
            [20, 10, 5, 5, 3, 0, null, false],
        ], loopCount: 3);

        $container = (new GifContainerReader())->read(new BinaryString($gif));

        self::assertEquals(new Dimensions(40, 30), $container->screen);
        self::assertSame(3, $container->loopCount);
        self::assertNotNull($container->globalColorTable);
        self::assertCount(3, $container->frames);
        [$first, $second, $third] = $container->frames;
        self::assertTrue($second->area->equals(new Rectangle(new Point(5, 6), new Dimensions(10, 12))));
        self::assertSame([GifDisposal::Keep, GifDisposal::RestoreBackground, GifDisposal::RestorePrevious], [$first->disposal, $second->disposal, $third->disposal]);
        self::assertSame([10, 25, 0], [$first->delayCentiseconds, $second->delayCentiseconds, $third->delayCentiseconds]);
        self::assertSame([null, 1, null], [$first->transparentIndex, $second->transparentIndex, $third->transparentIndex]);
        self::assertNull($first->localColorTable);
        self::assertNotNull($second->localColorTable);
        self::assertSame(2, $first->lzwMinimumCodeSize);
    }

    public function testMissingLoopExtensionMeansPlayOnce(): void
    {
        $container = (new GifContainerReader())->read(new BinaryString(ImageBytes::animatedGif(4, 4, [[0, 0, 4, 4, 0, 0, null, false]], loopCount: null)));

        self::assertNull($container->loopCount);
    }

    public function testZeroLogicalScreenIsSizedByTheFrames(): void
    {
        $container = (new GifContainerReader())->read(new BinaryString(ImageBytes::animatedGif(0, 0, [
            [0, 0, 4, 4, 0, 0, null, false],
            [10, 2, 6, 8, 0, 0, null, false],
        ])));

        self::assertEquals(new Dimensions(16, 10), $container->screen);
    }

    public function testStandaloneFrameIsAValidSingleImageGif(): void
    {
        $container = (new GifContainerReader())->read(new BinaryString(ImageBytes::animatedGif(40, 30, [
            [0, 0, 40, 30, 1, 10, null, false],
            [5, 6, 10, 12, 2, 25, 1, true],
        ])));

        $standalone = $container->standaloneFrame(1);
        $header = (new HeaderProbe())->probe(new BinaryString($standalone));
        $reread = (new GifContainerReader())->read(new BinaryString($standalone));

        self::assertEquals(new Dimensions(10, 12), $header->canvas);
        self::assertSame(1, $header->frameCount);
        self::assertTrue($header->hasAlpha);
        self::assertSame($container->frames[1]->imageData, $reread->frames[0]->imageData);
        self::assertSame(1, $reread->frames[0]->transparentIndex);
        self::assertSame($container->frames[1]->localColorTable, $reread->globalColorTable);
    }

    public function testFrameWithoutAnyColorTableIsCorrupted(): void
    {
        $container = (new GifContainerReader())->read(new BinaryString(ImageBytes::animatedGif(4, 4, [[0, 0, 4, 4, 0, 0, null, false]], globalTable: false)));

        $this->expectException(CorruptedImageException::class);

        $container->standaloneFrame(0);
    }

    public function testWriterProducesFullCanvasFramesThatClearBetweenFrames(): void
    {
        $frameGif = ImageBytes::animatedGif(8, 6, [[0, 0, 8, 6, 0, 0, 1, false]], loopCount: null);

        $written = (new GifContainerWriter())->write(new Dimensions(8, 6), 0, [new GifFrameSource($frameGif, 7), new GifFrameSource($frameGif, 12)]);
        $container = (new GifContainerReader())->read(new BinaryString($written));

        self::assertSame(0, $container->loopCount);
        self::assertNull($container->globalColorTable);
        self::assertCount(2, $container->frames);
        foreach ($container->frames as $frame) {
            self::assertTrue($frame->area->equals(Rectangle::covering(new Dimensions(8, 6))));
            self::assertSame(GifDisposal::RestoreBackground, $frame->disposal);
            self::assertNotNull($frame->localColorTable);
            self::assertSame(1, $frame->transparentIndex);
        }
        self::assertSame([7, 12], [$container->frames[0]->delayCentiseconds, $container->frames[1]->delayCentiseconds]);
        self::assertSame(2, (new HeaderProbe())->probe(new BinaryString($written))->frameCount);
    }

    public function testWriterOmitsLoopExtensionForPlayOnce(): void
    {
        $frameGif = ImageBytes::animatedGif(2, 2, [[0, 0, 2, 2, 0, 0, null, false]]);

        $written = (new GifContainerWriter())->write(new Dimensions(2, 2), null, [new GifFrameSource($frameGif, 0)]);

        self::assertStringNotContainsString('NETSCAPE', $written);
    }

    public function testMalformedContainersFailOnlyWithInvalidInput(): void
    {
        $gif = ImageBytes::animatedGif(20, 10, [[0, 0, 20, 10, 1, 10, null, false], [2, 2, 5, 5, 2, 5, 0, true]]);
        $candidates = [];
        for ($length = 0; $length < strlen($gif); ++$length) {
            $candidates[] = substr($gif, 0, $length);
        }
        mt_srand(7);
        for ($iteration = 0; $iteration < 3_000; ++$iteration) {
            $mutated = $gif;
            $mutated[mt_rand(6, strlen($gif) - 1)] = chr(mt_rand(0, 255));
            $candidates[] = $mutated;
        }

        foreach ($candidates as $candidate) {
            try {
                $container = (new GifContainerReader())->read(new BinaryString($candidate));
                foreach (array_keys($container->frames) as $index) {
                    $container->standaloneFrame($index);
                }
            } catch (InvalidInputException) {
            } catch (Throwable $unexpected) {
                self::fail(sprintf('%s: %s for %s', $unexpected::class, $unexpected->getMessage(), bin2hex($candidate)));
            }
        }
        $this->addToAssertionCount(1);
    }
}
