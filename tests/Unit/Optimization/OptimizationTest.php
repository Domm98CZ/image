<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Optimization;

use Domm98CZ\Image\Exception\ExternalToolException;
use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Format\Output\JpegOutput;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Metadata\MetadataEmbedder;
use Domm98CZ\Image\Metadata\MetadataExtractor;
use Domm98CZ\Image\Metadata\MetadataSet;
use Domm98CZ\Image\Optimization\External\PngquantOptimizer;
use Domm98CZ\Image\Optimization\External\ProcessResult;
use Domm98CZ\Image\Optimization\External\ProcessRunnerInterface;
use Domm98CZ\Image\Optimization\MetadataStripper;
use Domm98CZ\Image\Optimization\OptimizerChain;
use Domm98CZ\Image\Optimization\OptimizerInterface;
use Domm98CZ\Image\Tests\Fake\FakeDriver;
use Domm98CZ\Image\Tests\Fake\FakeImageFactory;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use Domm98CZ\Image\Watermark\MetadataWatermark;
use Domm98CZ\Image\Watermark\MetadataWatermarkReader;
use Domm98CZ\Image\Watermark\Payload;
use Domm98CZ\Image\Watermark\Steganography\LsbWatermark;
use Domm98CZ\Image\Watermark\WatermarkStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OptimizationTest extends TestCase
{
    /** @return iterable<string, array{EncodedImage}> */
    public static function withMetadata(): iterable
    {
        $metadata = new MetadataSet('<xmp/>', "II*\0exif", null);
        $embedder = new MetadataEmbedder();
        yield 'jpeg' => [$embedder->embed(new EncodedImage(ImageBytes::jpeg(12, 9), FormatName::Jpeg), $metadata)];
        yield 'png' => [$embedder->embed(new EncodedImage(ImageBytes::png(12, 9), FormatName::Png), $metadata)];
        yield 'webp' => [$embedder->embed(new EncodedImage(ImageBytes::webpLossless(12, 9, true), FormatName::Webp), $metadata)];
    }

    #[DataProvider('withMetadata')]
    public function testStripperRemovesMetadataAndKeepsTheImage(EncodedImage $image): void
    {
        $stripped = (new MetadataStripper())->optimize($image);

        self::assertEquals(new MetadataSet(), (new MetadataExtractor())->extract($stripped));
        self::assertLessThan($image->byteCount(), $stripped->byteCount());
        self::assertEquals((new HeaderProbe())->probe(new BinaryString($image->bytes)), (new HeaderProbe())->probe(new BinaryString($stripped->bytes)));
    }

    public function testJpegKeepsJfifAdobeAndIccUnlessAskedToDropTheProfile(): void
    {
        $jpeg = ImageBytes::jpeg(4, 4);
        $icc = "\xFF\xE2" . pack('n', 16) . "ICC_PROFILE\0\x01\x01";
        $adobe = "\xFF\xEE" . pack('n', 14) . "Adobe\0\x64\0\0\0\0\x01";
        $comment = "\xFF\xFE" . pack('n', 7) . 'hello';
        $image = new EncodedImage(substr($jpeg, 0, 20) . $icc . $adobe . $comment . substr($jpeg, 20), FormatName::Jpeg);

        $kept = (new MetadataStripper())->optimize($image)->bytes;
        $dropped = (new MetadataStripper(keepColorProfile: false))->optimize($image)->bytes;

        self::assertStringStartsWith("\xFF\xD8\xFF\xE0", $kept);
        self::assertStringContainsString('ICC_PROFILE', $kept);
        self::assertStringContainsString('Adobe', $kept);
        self::assertStringNotContainsString('hello', $kept);
        self::assertStringNotContainsString('ICC_PROFILE', $dropped);
        self::assertStringContainsString('Adobe', $dropped);
    }

    public function testWebpFlagsNoLongerAnnounceRemovedChunks(): void
    {
        [$image] = iterator_to_array(self::withMetadata())['webp'];

        $stripped = new BinaryString((new MetadataStripper())->optimize($image)->bytes);

        self::assertSame('VP8X', $stripped->slice(12, 4));
        self::assertSame(0, $stripped->uint8(20) & 0x0C);
        self::assertSame($stripped->length() - 8, $stripped->uint32LittleEndian(4));
    }

    public function testGifPassesThrough(): void
    {
        $gif = new EncodedImage(ImageBytes::gif(2, 2, [[2, 2]]), FormatName::Gif);

        self::assertSame($gif->bytes, (new MetadataStripper())->optimize($gif)->bytes);
    }

    public function testHeicPassesThrough(): void
    {
        $heic = new EncodedImage(ImageBytes::heic(), FormatName::Heic);

        self::assertSame($heic->bytes, (new MetadataStripper())->optimize($heic)->bytes);
    }

    /** @return iterable<string, array{ProcessResult}> */
    public static function keepOriginal(): iterable
    {
        yield 'quality too low' => [new ProcessResult(99, '', 'too low')];
        yield 'not smaller' => [new ProcessResult(98, '', '')];
    }

    #[DataProvider('keepOriginal')]
    public function testPngquantKeepsTheOriginalWhenItIsNotWorthIt(ProcessResult $result): void
    {
        $input = new EncodedImage(ImageBytes::png(12, 9), FormatName::Png);

        self::assertSame($input, (new PngquantOptimizer(runner: self::runner($result)))->optimize($input));
    }

    /** @return iterable<string, array{ProcessResult}> */
    public static function failures(): iterable
    {
        yield 'crash' => [new ProcessResult(1, '', 'boom')];
        yield 'not a png' => [new ProcessResult(0, 'garbage', '')];
    }

    #[DataProvider('failures')]
    public function testPngquantFailuresAndUntrustworthyOutputAreReported(ProcessResult $result): void
    {
        $this->expectException(ExternalToolException::class);

        (new PngquantOptimizer(runner: self::runner($result)))->optimize(new EncodedImage(ImageBytes::png(12, 9) . str_repeat("\0", 100), FormatName::Png));
    }

    public function testPngquantLeavesOtherFormatsAloneWithoutRunningAnything(): void
    {
        $runner = self::runner(new ProcessResult(1, '', ''));
        $jpeg = new EncodedImage(ImageBytes::jpeg(2, 2), FormatName::Jpeg);

        self::assertSame($jpeg, (new PngquantOptimizer(runner: $runner))->optimize($jpeg));
        self::assertSame([], $runner->commands);
    }

    public function testOptimizersRunBeforeMetadataWatermarksSoStrippingCannotRemoveThem(): void
    {
        $seen = [];
        $spy = new class ($seen) implements OptimizerInterface {
            /** @param list<string> $seen */
            public function __construct(private array &$seen) {}

            public function optimize(EncodedImage $image): EncodedImage
            {
                $this->seen[] = $image->bytes;

                return $image;
            }
        };

        $encoded = FakeImageFactory::with(new FakeDriver())->openBytes(ImageBytes::png(8, 8))
            ->watermark(new MetadataWatermark(Payload::text('kept')))
            ->optimize(new OptimizerChain([$spy, new MetadataStripper()]))
            ->encode(new PngOutput());

        self::assertCount(1, $seen);
        self::assertStringNotContainsString('dmwm', $seen[0]);
        self::assertSame(WatermarkStatus::Intact, (new MetadataWatermarkReader())->read($encoded)->status);
    }

    public function testEncodeSmallestProcessesOnceAndReturnsTheSmallestCandidate(): void
    {
        $driver = new FakeDriver(encodedByteCounts: ['jpeg' => 500, 'webp' => 300, 'avif' => 400]);

        $smallest = FakeImageFactory::with($driver)->openBytes(ImageBytes::png(20, 10))->scale(0.5)
            ->encodeSmallest(new JpegOutput(), new WebpOutput(), new AvifOutput());

        self::assertSame(FormatName::Webp, $smallest->format);
        self::assertSame(['decode png 20x10 -> #1', 'apply #1 resize 10x5', 'encode #1 jpeg', 'encode #1 webp', 'encode #1 avif'], $driver->calls);
    }

    public function testEncodeSmallestStillProtectsLsbWatermarks(): void
    {
        $builder = FakeImageFactory::with(new FakeDriver())->openBytes(ImageBytes::png(20, 20))->watermark(new LsbWatermark(Payload::text('x')));

        $this->expectException(InvalidWatermarkException::class);

        $builder->encodeSmallest(new PngOutput(), new JpegOutput());
    }

    private static function runner(ProcessResult $result): RecordingRunner
    {
        return new RecordingRunner($result);
    }
}

final class RecordingRunner implements ProcessRunnerInterface
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<int> */
    public array $limits = [];

    public function __construct(private readonly ProcessResult $result) {}

    public function run(array $command, string $stdin, float $timeoutSeconds, int $maxOutputBytes): ProcessResult
    {
        $this->commands[] = $command;
        $this->limits[] = $maxOutputBytes;

        return $this->result;
    }
}
