<?php

declare(strict_types=1);

namespace Domm98CZ\Image;

use Domm98CZ\Image\Animation\AnimatedImage;
use Domm98CZ\Image\Animation\AnimationCodecs;
use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Operation\FluentOperations;
use Domm98CZ\Image\Operation\OperationInterface;
use Domm98CZ\Image\Optimization\OptimizerInterface;
use Domm98CZ\Image\Output\EncodingTerminals;
use Domm98CZ\Image\Output\FileWriter;
use Domm98CZ\Image\Security\LimitChecker;
use Domm98CZ\Image\Security\LimitType;
use Domm98CZ\Image\Watermark\EncodedWatermarkInterface;
use Domm98CZ\Image\Watermark\PixelWatermarkInterface;
use Domm98CZ\Image\Watermark\WatermarkInterface;
use Domm98CZ\Image\Watermark\WatermarkSchedule;
use Psr\Http\Message\StreamInterface;

// Same fluent operations as ImageBuilder, applied identically to every (fully composited) frame.
final readonly class AnimationBuilder
{
    use EncodingTerminals;
    use FluentOperations;

    /**
     * @internal Created by ImageFactory.
     * @param list<OperationInterface> $operations
     * @param list<EncodedWatermarkInterface> $encodedWatermarks
     * @param list<OptimizerInterface> $optimizers
     */
    public function __construct(
        private AnimatedImage $source,
        private ImageFactory $images,
        private AnimationCodecs $codecs,
        private LimitChecker $limits,
        private int $maxAnimationPixels,
        private FileWriter $writer,
        private array $operations = [],
        private array $encodedWatermarks = [],
        private array $optimizers = [],
    ) {}

    public static function open(string $path): self
    {
        return (new ImageFactory())->openAnimation($path);
    }

    public static function openBytes(string $bytes): self
    {
        return (new ImageFactory())->openAnimationBytes($bytes);
    }

    public static function openStream(StreamInterface $stream): self
    {
        return (new ImageFactory())->openAnimationStream($stream);
    }

    /** @param list<Frame> $frames */
    public static function fromFrames(array $frames, int $playCount = 0): self
    {
        return (new ImageFactory())->animation($frames, $playCount);
    }

    public function apply(OperationInterface $operation): static
    {
        return $this->withState($this->source, [...$this->operations, $operation]);
    }

    /** @param list<OperationInterface> $operations */
    public function applyAll(array $operations): self
    {
        return $this->withState($this->source, [...$this->operations, ...$operations]);
    }

    public function watermark(WatermarkInterface $watermark): self
    {
        return match (true) {
            $watermark instanceof PixelWatermarkInterface => $this->apply($watermark),
            $watermark instanceof EncodedWatermarkInterface => new self($this->source, $this->images, $this->codecs, $this->limits, $this->maxAnimationPixels, $this->writer, $this->operations, [...$this->encodedWatermarks, $watermark], $this->optimizers),
            default => throw InvalidWatermarkException::unknownKind($watermark::class),
        };
    }

    public function optimize(OptimizerInterface $optimizer): self
    {
        return new self($this->source, $this->images, $this->codecs, $this->limits, $this->maxAnimationPixels, $this->writer, $this->operations, $this->encodedWatermarks, [...$this->optimizers, $optimizer]);
    }

    public function withFrameDelay(int $delayMilliseconds): self
    {
        return $this->withState(
            $this->source->withFrames(array_map(static fn(Frame $frame): Frame => $frame->withDelay($delayMilliseconds), $this->source->frames)),
            $this->operations,
        );
    }

    public function withPlayCount(int $playCount): self
    {
        return $this->withState($this->source->withPlayCount($playCount), $this->operations);
    }

    /** @return list<OperationInterface> */
    public function operations(): array
    {
        return $this->operations;
    }

    public function toAnimation(): AnimatedImage
    {
        if ($this->encodedWatermarks !== []) {
            throw InvalidWatermarkException::encodedWatermarkNeedsEncoding();
        }

        return $this->process();
    }

    private function process(): AnimatedImage
    {
        if ($this->operations === []) {
            return $this->source;
        }

        $frames = [];
        foreach ($this->source->frames as $index => $frame) {
            $processed = $frame->withImage($this->images->from($frame->image)->applyAll($this->operations)->toImage());
            if ($index === 0) {
                // Every frame ends up the same size, so the first one tells whether the whole animation fits.
                $this->limits->check(LimitType::AnimationPixels, $processed->image->dimensions->pixelCount() * $this->source->frameCount(), $this->maxAnimationPixels);
            }
            $frames[] = $processed;
        }

        return $this->source->withFrames($frames);
    }

    public function encode(OutputFormatInterface $output): EncodedImage
    {
        $codec = $this->codecs->encoderFor($output);
        WatermarkSchedule::assertSurvives($this->operations, $output);

        return $this->finish($this->optimized($codec->encode($this->process(), $output)));
    }

    // Processes once, encodes every candidate (optimizers included, they can change the ranking), keeps the smallest.
    public function encodeSmallest(OutputFormatInterface ...$candidates): EncodedImage
    {
        if ($candidates === []) {
            throw InvalidOperationException::noCandidates();
        }
        $encoders = [];
        foreach ($candidates as $candidate) {
            $codec = $this->codecs->encoderFor($candidate);
            WatermarkSchedule::assertSurvives($this->operations, $candidate);
            $encoders[] = [$codec, $candidate];
        }
        $animation = $this->process();
        $best = null;
        foreach ($encoders as [$codec, $candidate]) {
            $encoded = $this->optimized($codec->encode($animation, $candidate));
            if ($best === null || $encoded->byteCount() < $best->byteCount()) {
                $best = $encoded;
            }
        }

        return $this->finish($best);
    }

    private function optimized(EncodedImage $encoded): EncodedImage
    {
        foreach ($this->optimizers as $optimizer) {
            $encoded = $optimizer->optimize($encoded);
        }

        return $encoded;
    }

    // Metadata watermarks go last so no optimizer can strip them.
    private function finish(EncodedImage $encoded): EncodedImage
    {
        foreach ($this->encodedWatermarks as $watermark) {
            $encoded = $watermark->apply($encoded);
        }

        return $encoded;
    }

    private function fileWriter(): FileWriter
    {
        return $this->writer;
    }

    /** @param list<OperationInterface> $operations */
    private function withState(AnimatedImage $source, array $operations): self
    {
        return new self($source, $this->images, $this->codecs, $this->limits, $this->maxAnimationPixels, $this->writer, $operations, $this->encodedWatermarks, $this->optimizers);
    }
}
