<?php

declare(strict_types=1);

namespace Domm98CZ\Image;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Operation\FluentOperations;
use Domm98CZ\Image\Operation\OperationInterface;
use Domm98CZ\Image\Optimization\OptimizerInterface;
use Domm98CZ\Image\Output\EncodingTerminals;
use Domm98CZ\Image\Output\FileWriter;
use Domm98CZ\Image\Pipeline\Executor;
use Domm98CZ\Image\Pipeline\Planner;
use Domm98CZ\Image\Pipeline\Source\ImageSource;
use Domm98CZ\Image\Pipeline\Source\SourceInterface;
use Domm98CZ\Image\Watermark\EncodedWatermarkInterface;
use Domm98CZ\Image\Watermark\PixelWatermarkInterface;
use Domm98CZ\Image\Watermark\WatermarkInterface;
use Domm98CZ\Image\Watermark\WatermarkSchedule;
use Psr\Http\Message\StreamInterface;

final readonly class ImageBuilder
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
        private SourceInterface $source,
        private Planner $planner,
        private Executor $executor,
        private FileWriter $writer,
        private array $operations = [],
        private array $encodedWatermarks = [],
        private array $optimizers = [],
    ) {}

    public static function open(string $path): self
    {
        return (new ImageFactory())->open($path);
    }

    public static function openBytes(string $bytes): self
    {
        return (new ImageFactory())->openBytes($bytes);
    }

    public static function openStream(StreamInterface $stream): self
    {
        return (new ImageFactory())->openStream($stream);
    }

    public static function from(Image $image): self
    {
        return (new ImageFactory())->from($image);
    }

    public static function create(Dimensions $dimensions, ?Color $background = null): self
    {
        return (new ImageFactory())->create($dimensions, $background);
    }

    public function apply(OperationInterface $operation): static
    {
        return $this->with(operations: [...$this->operations, $operation]);
    }

    /** @param list<OperationInterface> $operations */
    public function applyAll(array $operations): self
    {
        return $this->with(operations: [...$this->operations, ...$operations]);
    }

    public function watermark(WatermarkInterface $watermark): self
    {
        return match (true) {
            $watermark instanceof PixelWatermarkInterface => $this->apply($watermark),
            $watermark instanceof EncodedWatermarkInterface => $this->with(encodedWatermarks: [...$this->encodedWatermarks, $watermark]),
            default => throw InvalidWatermarkException::unknownKind($watermark::class),
        };
    }

    public function optimize(OptimizerInterface $optimizer): self
    {
        return $this->with(optimizers: [...$this->optimizers, $optimizer]);
    }

    /** @return list<OperationInterface> */
    public function operations(): array
    {
        return $this->operations;
    }

    public function toImage(): Image
    {
        if ($this->encodedWatermarks !== []) {
            throw InvalidWatermarkException::encodedWatermarkNeedsEncoding();
        }

        return $this->run(null);
    }

    public function encode(OutputFormatInterface $output): EncodedImage
    {
        WatermarkSchedule::assertSurvives($this->operations, $output);

        return $this->finish($this->optimized($this->run($output)->encode($output)));
    }

    // Processes once, encodes every candidate (optimizers included, they can change the ranking), keeps the smallest.
    public function encodeSmallest(OutputFormatInterface ...$candidates): EncodedImage
    {
        if ($candidates === []) {
            throw InvalidOperationException::noCandidates();
        }
        foreach ($candidates as $candidate) {
            WatermarkSchedule::assertSurvives($this->operations, $candidate);
        }
        $image = $this->run(null);
        $best = null;
        foreach ($candidates as $candidate) {
            $encoded = $this->optimized((new self(new ImageSource($image), $this->planner, $this->executor, $this->writer))->encode($candidate));
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

    /**
     * @param ?list<OperationInterface> $operations
     * @param ?list<EncodedWatermarkInterface> $encodedWatermarks
     * @param ?list<OptimizerInterface> $optimizers
     */
    private function with(?array $operations = null, ?array $encodedWatermarks = null, ?array $optimizers = null): self
    {
        return new self(
            $this->source,
            $this->planner,
            $this->executor,
            $this->writer,
            $operations ?? $this->operations,
            $encodedWatermarks ?? $this->encodedWatermarks,
            $optimizers ?? $this->optimizers,
        );
    }

    private function run(?OutputFormatInterface $output): Image
    {
        $operations = WatermarkSchedule::order($this->operations);

        return $this->executor->run($this->planner->plan($this->source, $operations, $output), $this->source);
    }

    private function fileWriter(): FileWriter
    {
        return $this->writer;
    }
}
