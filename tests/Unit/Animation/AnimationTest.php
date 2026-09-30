<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Animation;

use Domm98CZ\Image\Animation\AnimatedImage;
use Domm98CZ\Image\Animation\AnimationCodecInterface;
use Domm98CZ\Image\Animation\AnimationCodecs;
use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\AnimationBuilder;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Exception\InvalidAnimationException;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Output\GifOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Optimization\OptimizerInterface;
use Domm98CZ\Image\Output\FileWriter;
use Domm98CZ\Image\Security\LimitChecker;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Security\ValidatedInput;
use Domm98CZ\Image\Tests\Fake\FakeDriver;
use Domm98CZ\Image\Tests\Fake\FakeImageFactory;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use Domm98CZ\Image\Watermark\EncodedWatermarkInterface;
use Domm98CZ\Image\Watermark\Payload;
use Domm98CZ\Image\Watermark\Steganography\LsbWatermark;
use PHPUnit\Framework\TestCase;

final class AnimationTest extends TestCase
{
    public function testAnimatedImageValidatesFrames(): void
    {
        $factory = FakeImageFactory::with(new FakeDriver());
        $small = self::image($factory, 4, 4);
        $large = self::image($factory, 5, 4);
        $failures = [];
        foreach ([
            'no frames' => static fn() => new AnimatedImage([]),
            'size mismatch' => static fn() => new AnimatedImage([new Frame($small), new Frame($large)]),
            'negative play count' => static fn() => new AnimatedImage([new Frame($small)], -1),
            'huge play count' => static fn() => new AnimatedImage([new Frame($small)], 70_000),
            'negative delay' => static fn() => new Frame($small, -1),
            'huge delay' => static fn() => new Frame($small, Frame::MAX_DELAY_MILLISECONDS + 1),
        ] as $case => $create) {
            try {
                $create();
            } catch (InvalidAnimationException) {
                $failures[] = $case;
            }
        }

        self::assertSame(['no frames', 'size mismatch', 'negative play count', 'huge play count', 'negative delay', 'huge delay'], $failures);
    }

    public function testDurationAndWithers(): void
    {
        $image = self::image(FakeImageFactory::with(new FakeDriver()), 2, 2);
        $animation = new AnimatedImage([new Frame($image, 100), new Frame($image, 250)]);

        self::assertSame(350, $animation->durationMilliseconds());
        self::assertSame(2, $animation->frameCount());
        self::assertSame(1, $animation->withPlayCount(1)->playCount);
        self::assertSame(0, $animation->playCount);
    }

    public function testOperationsRunOnEveryFrameWithTheSameDriverCalls(): void
    {
        $driver = new FakeDriver();
        $factory = FakeImageFactory::with($driver);
        $frames = [new Frame(self::image($factory, 40, 20), 50), new Frame(self::image($factory, 40, 20), 80)];
        $driver->calls = [];

        $animation = $factory->animation($frames)->scale(0.5)->grayscale()->withFrameDelay(120)->toAnimation();

        self::assertSame([
            'copy #1 -> #3', 'apply #3 resize 20x10', 'apply #3 grayscale',
            'copy #2 -> #4', 'apply #4 resize 20x10', 'apply #4 grayscale',
        ], $driver->calls);
        self::assertEquals(new Dimensions(20, 10), $animation->dimensions);
        self::assertSame([120, 120], array_map(static fn(Frame $frame): int => $frame->delayMilliseconds, $animation->frames));
    }

    public function testBuilderIsImmutable(): void
    {
        $factory = FakeImageFactory::with(new FakeDriver());
        $original = $factory->animation([new Frame(self::image($factory, 4, 4), 10)]);

        $changed = $original->scale(2.0)->withPlayCount(5)->withFrameDelay(99);

        self::assertSame([], $original->operations());
        self::assertSame(0, $original->toAnimation()->playCount);
        self::assertSame(10, $original->toAnimation()->frames[0]->delayMilliseconds);
        self::assertSame(5, $changed->toAnimation()->playCount);
    }

    public function testApplyAllAppendsStoredOperations(): void
    {
        $factory = FakeImageFactory::with(new FakeDriver());
        $frames = [new Frame(self::image($factory, 4, 4))];
        $stored = $factory->animation($frames)->scale(0.5)->grayscale()->operations();
        $original = $factory->animation($frames)->invert();

        $reused = $original->applyAll($stored);

        self::assertSame([...$original->operations(), ...$stored], $reused->operations());
        self::assertCount(1, $original->operations());
    }

    public function testEncodeSmallestProcessesOnceAndKeepsTheSmallestOptimizedCandidate(): void
    {
        $driver = new FakeDriver();
        $factory = FakeImageFactory::with($driver);
        $frames = [new Frame(self::image($factory, 40, 20)), new Frame(self::image($factory, 40, 20))];
        $gif = new FakeAnimationCodec(FormatName::Gif, 500);
        $webp = new FakeAnimationCodec(FormatName::Webp, 300);
        // Halves GIFs only, so the raw ranking (WebP smaller) flips after optimizing.
        $halveGif = new class implements OptimizerInterface {
            public function optimize(EncodedImage $image): EncodedImage
            {
                return $image->format === FormatName::Gif ? new EncodedImage(substr($image->bytes, 0, intdiv($image->byteCount(), 2)), $image->format) : $image;
            }
        };
        $tag = new class implements EncodedWatermarkInterface {
            public function apply(EncodedImage $image): EncodedImage
            {
                return new EncodedImage($image->bytes . 'wm', $image->format);
            }
        };
        $driver->calls = [];

        $smallest = self::builder($factory, new AnimatedImage($frames), [$gif, $webp])
            ->scale(0.5)
            ->optimize($halveGif)
            ->watermark($tag)
            ->encodeSmallest(new WebpOutput(), new GifOutput());

        self::assertSame(FormatName::Gif, $smallest->format);
        self::assertSame(252, $smallest->byteCount());
        self::assertStringEndsWith('wm', $smallest->bytes);
        self::assertSame(['copy #1 -> #3', 'apply #3 resize 20x10', 'copy #2 -> #4', 'apply #4 resize 20x10'], $driver->calls);
        self::assertSame([1, 1], [$gif->encodes, $webp->encodes]);
    }

    public function testEncodeSmallestNeedsAtLeastOneCandidate(): void
    {
        $factory = FakeImageFactory::with(new FakeDriver());
        $builder = $factory->animation([new Frame(self::image($factory, 2, 2))]);

        $this->expectException(InvalidOperationException::class);

        $builder->encodeSmallest();
    }

    public function testEncodeSmallestStillProtectsLsbWatermarks(): void
    {
        $factory = FakeImageFactory::with(new FakeDriver());
        $builder = $factory->animation([new Frame(self::image($factory, 20, 20))])->watermark(new LsbWatermark(Payload::text('x')));

        $this->expectException(InvalidWatermarkException::class);

        $builder->encodeSmallest(new GifOutput());
    }

    public function testResultingAnimationIsCheckedAgainstTheAnimationPixelLimit(): void
    {
        $factory = FakeImageFactory::configured(new Configuration(Limits::default()->withMaxAnimationPixels(1_000)), new FakeDriver());
        $frames = array_fill(0, 3, new Frame(self::image($factory, 10, 10)));

        $this->expectException(LimitExceededException::class);

        $factory->animation($frames)->scale(2.0)->toAnimation();
    }

    public function testDecodesGifFramesThroughAnyDriver(): void
    {
        $driver = new FakeDriver();
        $gif = ImageBytes::animatedGif(20, 10, [[0, 0, 20, 10, 1, 10, null, false], [2, 2, 5, 5, 2, 25, 0, true]], loopCount: 4);

        $animation = FakeImageFactory::with($driver)->openAnimationBytes($gif)->toAnimation();

        self::assertSame(2, $animation->frameCount());
        // NETSCAPE2.0 value 4 means four repetitions after the first play.
        self::assertSame(5, $animation->playCount);
        self::assertSame([100, 250], array_map(static fn(Frame $frame): int => $frame->delayMilliseconds, $animation->frames));
        self::assertEquals(new Dimensions(20, 10), $animation->dimensions);
        self::assertSame(FormatName::Gif, $animation->sourceFormat);
        self::assertContains('decode gif 5x5 -> #2', $driver->calls);
    }

    public function testStillImageOpensAsASingleFrameThatPlaysOnce(): void
    {
        $animation = FakeImageFactory::with(new FakeDriver())->openAnimationBytes(ImageBytes::png(6, 4))->toAnimation();

        self::assertSame(1, $animation->frameCount());
        self::assertSame(1, $animation->playCount);
    }

    public function testEncodesGifThroughTheContainerWriter(): void
    {
        $factory = FakeImageFactory::with(new FakeDriver());
        $frames = [new Frame(self::image($factory, 8, 6), 100), new Frame(self::image($factory, 8, 6), 200)];

        $encoded = $factory->animation($frames)->encode(new GifOutput());

        self::assertSame(2, (new HeaderProbe())->probe(new BinaryString($encoded->bytes))->frameCount);
    }

    public function testRejectsFormatsThatCannotHoldAnimations(): void
    {
        $factory = FakeImageFactory::with(new FakeDriver());

        $this->expectExceptionObject(UnsupportedFormatException::cannotEncodeAnimation(FormatName::Png));

        $factory->animation([new Frame(self::image($factory, 2, 2))])->encode(new PngOutput());
    }

    public function testAnimatedWebpNeedsImagick(): void
    {
        $factory = FakeImageFactory::with(new FakeDriver(DriverName::Gd));

        $this->expectExceptionObject(UnsupportedFormatException::noAnimationCodec(FormatName::Webp));

        $factory->animation([new Frame(self::image($factory, 2, 2))])->encode(new WebpOutput());
    }

    private static function image(ImageFactory $factory, int $width, int $height): Image
    {
        return $factory->create(new Dimensions($width, $height), Color::white())->toImage();
    }

    /** @param list<AnimationCodecInterface> $codecs */
    private static function builder(ImageFactory $factory, AnimatedImage $animation, array $codecs): AnimationBuilder
    {
        $limits = Limits::default();

        return new AnimationBuilder($animation, $factory, new AnimationCodecs($codecs), new LimitChecker($limits), $limits->maxAnimationPixels, new FileWriter());
    }
}

final class FakeAnimationCodec implements AnimationCodecInterface
{
    public int $encodes = 0;

    public function __construct(
        private readonly FormatName $format,
        private readonly int $byteCount,
    ) {}

    public function format(): FormatName
    {
        return $this->format;
    }

    public function canDecode(): bool
    {
        return false;
    }

    public function canEncode(): bool
    {
        return true;
    }

    public function decode(ValidatedInput $input): AnimatedImage
    {
        throw new \LogicException('This fake only encodes.');
    }

    public function encode(AnimatedImage $animation, OutputFormatInterface $output): EncodedImage
    {
        $this->encodes++;

        return new EncodedImage(str_repeat('.', $this->byteCount), $this->format);
    }
}
