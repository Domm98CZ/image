<?php

declare(strict_types=1);

namespace Domm98CZ\Image;

use Domm98CZ\Image\Animation\AnimatedImage;
use Domm98CZ\Image\Animation\AnimationCodecs;
use Domm98CZ\Image\Animation\Frame;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\Text;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Metadata\ColorProfileReader;
use Domm98CZ\Image\Metadata\ExifOrientationReader;
use Domm98CZ\Image\Operation\Draw;
use Domm98CZ\Image\Output\FileWriter;
use Domm98CZ\Image\Pipeline\Executor;
use Domm98CZ\Image\Pipeline\Planner;
use Domm98CZ\Image\Pipeline\Source\BlankSource;
use Domm98CZ\Image\Pipeline\Source\DecodableSource;
use Domm98CZ\Image\Pipeline\Source\ImageSource;
use Domm98CZ\Image\Pipeline\Source\SourceInterface;
use Domm98CZ\Image\Pipeline\Transfer;
use Domm98CZ\Image\Security\InputGuard;
use Domm98CZ\Image\Security\InputReader;
use Domm98CZ\Image\Security\LimitChecker;
use Domm98CZ\Image\Security\MemoryBudget;
use Domm98CZ\Image\Security\ValidatedInput;
use Psr\Http\Message\StreamInterface;

final readonly class ImageFactory
{
    private InputReader $reader;
    private InputGuard $guard;
    private LimitChecker $limits;
    private Planner $planner;
    private Executor $executor;
    private AnimationCodecs $animationCodecs;
    private DriverRegistry $drivers;

    public function __construct(
        private Configuration $configuration = new Configuration(),
        ?DriverRegistry $drivers = null,
        private FileWriter $writer = new FileWriter(),
    ) {
        $registry = ($drivers ?? DriverRegistry::detect($configuration))->restrictedTo($configuration);
        $this->drivers = $registry;
        $this->reader = new InputReader($configuration->limits);
        $this->guard = new InputGuard($configuration->limits);
        $this->limits = new LimitChecker($configuration->limits);
        $this->planner = new Planner($registry, $configuration->costModel);
        $transfer = new Transfer($this->guard);
        $this->executor = new Executor($this->limits, $transfer);
        $this->animationCodecs = AnimationCodecs::forDrivers($registry, $this->guard, $transfer);
    }

    public function configuration(): Configuration
    {
        return $this->configuration;
    }

    public function open(string $path): ImageBuilder
    {
        return $this->fromUntrustedBytes($this->reader->fromFile($path));
    }

    public function openBytes(string $bytes): ImageBuilder
    {
        return $this->fromUntrustedBytes($this->reader->fromBytes($bytes));
    }

    public function openStream(StreamInterface $stream): ImageBuilder
    {
        return $this->fromUntrustedBytes($this->reader->fromStream($stream));
    }

    // An Image may come from a factory with looser limits, so this one re-checks its own before any work.
    public function from(Image $image): ImageBuilder
    {
        $this->limits->checkDimensions($image->dimensions);

        return $this->builder(new ImageSource($image));
    }

    // Tight, transparent image of rendered text, e.g. for a text watermark that must be anchored to a corner.
    // The canvas fits every ink pixel by itself; padding only adds breathing room around it.
    public function text(string $text, Font $font, Color $color, int $padding = 0): Image
    {
        $padding = max(0, $padding);
        // Each driver measures its own rendering, so the measuring driver must be the one the planner will draw with.
        // Costs are per pixel, so a nominal canvas predicts that choice; the plan for the measured canvas confirms it.
        $driver = $this->drawingDriver(new Dimensions(1, 1), new Text($text, Point::origin(), $font, $color));
        $attempts = count($this->drivers->drivers);
        do {
            $bounds = $driver->measureText($text, $font);
            $canvas = new Dimensions($bounds->dimensions->width + 2 * $padding, $bounds->dimensions->height + 2 * $padding);
            $shape = new Text($text, new Point($padding - $bounds->left(), $padding - $bounds->top()), $font, $color);
            $planned = $this->drawingDriver($canvas, $shape);
            $settled = $planned === $driver;
            $driver = $planned;
        } while (!$settled && --$attempts > 0);

        return $this->create($canvas)->draw(static fn(Canvas $c): Canvas => $c->add($shape))->toImage();
    }

    // The driver the pipeline behind create()->draw()->toImage() paints with on a canvas of this size.
    private function drawingDriver(Dimensions $canvas, Text $shape): DriverInterface
    {
        return $this->planner->plan(new BlankSource($canvas, Color::transparent()), [new Draw([$shape])])->finalDriver();
    }

    public function create(Dimensions $dimensions, ?Color $background = null): ImageBuilder
    {
        $this->limits->checkDimensions($dimensions);

        return $this->builder(new BlankSource($dimensions, $background ?? Color::transparent()));
    }

    public function openAnimation(string $path): AnimationBuilder
    {
        return $this->animationFromUntrustedBytes($this->reader->fromFile($path));
    }

    public function openAnimationBytes(string $bytes): AnimationBuilder
    {
        return $this->animationFromUntrustedBytes($this->reader->fromBytes($bytes));
    }

    public function openAnimationStream(StreamInterface $stream): AnimationBuilder
    {
        return $this->animationFromUntrustedBytes($this->reader->fromStream($stream));
    }

    /** @param list<Frame> $frames */
    public function animation(array $frames, int $playCount = 0): AnimationBuilder
    {
        $animation = new AnimatedImage($frames, $playCount);
        // Every frame has the animation's size (AnimatedImage guarantees it), so one dimension check covers them all.
        $this->limits->checkDimensions($animation->dimensions);
        $this->limits->checkAnimation($animation->frameCount(), $animation->dimensions);

        return $this->animationBuilder($animation);
    }

    // Still images open as a one-frame animation, so callers can treat every input alike.
    private function animationFromUntrustedBytes(string $bytes): AnimationBuilder
    {
        $input = $this->guard->inspect($bytes, MemoryBudget::fromRuntime());
        if ($input->header->isAnimated()) {
            return $this->animationBuilder($this->animationCodecs->decoderFor($input->header->format)->decode($input));
        }
        $image = $this->builder($this->decodable($input))->toImage();

        return $this->animationBuilder(new AnimatedImage([new Frame($image, 0)], 1, $input->header->format));
    }

    private function animationBuilder(AnimatedImage $animation): AnimationBuilder
    {
        return new AnimationBuilder($animation, $this, $this->animationCodecs, $this->limits, $this->configuration->limits->maxAnimationPixels, $this->writer);
    }

    private function decodable(ValidatedInput $input): DecodableSource
    {
        $bytes = new BinaryString($input->bytes);

        return new DecodableSource(
            $input,
            (new ExifOrientationReader())->read($bytes, $input->header->format),
            (new ColorProfileReader())->read($bytes, $input->header->format),
        );
    }

    // Everything is validated here, eagerly, so a bad input fails at open() rather than at save().
    private function fromUntrustedBytes(string $bytes): ImageBuilder
    {
        $input = $this->guard->inspect($bytes, MemoryBudget::fromRuntime());
        return $this->builder($this->decodable($input));
    }

    private function builder(SourceInterface $source): ImageBuilder
    {
        return new ImageBuilder($source, $this->planner, $this->executor, $this->writer);
    }
}
