<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Fake;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\ImageHandleInterface;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\IncompatibleHandleException;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Operation\Blur;
use Domm98CZ\Image\Operation\Brightness;
use Domm98CZ\Image\Operation\Colorize;
use Domm98CZ\Image\Operation\Composite;
use Domm98CZ\Image\Operation\Contrast;
use Domm98CZ\Image\Operation\Crop;
use Domm98CZ\Image\Operation\Draw;
use Domm98CZ\Image\Operation\Flip;
use Domm98CZ\Image\Operation\Gamma;
use Domm98CZ\Image\Operation\Grayscale;
use Domm98CZ\Image\Operation\Invert;
use Domm98CZ\Image\Operation\Pad;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Operation\Resize;
use Domm98CZ\Image\Operation\Rotate;
use Domm98CZ\Image\Operation\Sharpen;
use Domm98CZ\Image\Operation\Trim;
use Domm98CZ\Image\Security\ValidatedInput;
use Domm98CZ\Image\Tests\Support\ImageBytes;

// Records every call as a readable line so tests can assert the exact driver conversation.
final class FakeDriver implements DriverInterface
{
    /** @var list<string> */
    public array $calls = [];

    private int $nextHandleId = 1;

    /**
     * @param list<class-string<PrimitiveOperationInterface>> $unsupportedOperations
     * @param list<FormatName> $undecodable
     * @param list<FormatName> $unencodable
     * @param list<class-string<PrimitiveOperationInterface>> $fallbackOperations
     * @param list<FormatName> $degradedEncoding
     * @param list<FormatName> $degradedDecoding
     * @param list<class-string<PrimitiveOperationInterface>> $degradedOperations
     * @param array<string, int> $encodedByteCounts format value => padded size of fake (non PNG/GIF) output
     */
    public function __construct(
        private readonly DriverName $name = DriverName::Gd,
        private readonly array $unsupportedOperations = [],
        private readonly array $undecodable = [],
        private readonly array $unencodable = [],
        private readonly Color $pixelColor = new Color(10, 20, 30),
        private readonly array $fallbackOperations = [],
        private readonly array $degradedEncoding = [],
        private readonly array $degradedDecoding = [],
        private readonly Support $pixelAccess = Support::Native,
        private readonly array $degradedOperations = [],
        private readonly array $encodedByteCounts = [],
        private readonly Support $colorManagement = Support::Native,
    ) {}

    public function colorManagement(): Support
    {
        return $this->colorManagement;
    }

    public function pixelAccess(): Support
    {
        return $this->pixelAccess;
    }

    public function name(): DriverName
    {
        return $this->name;
    }

    public function decodingSupport(FormatName $format): Support
    {
        return match (true) {
            in_array($format, $this->undecodable, true) => Support::None,
            in_array($format, $this->degradedDecoding, true) => Support::Degraded,
            default => Support::Native,
        };
    }

    public function encodingSupport(OutputFormatInterface $output): Support
    {
        return match (true) {
            in_array($output->format(), $this->unencodable, true) => Support::None,
            in_array($output->format(), $this->degradedEncoding, true) => Support::Degraded,
            default => Support::Native,
        };
    }

    public function support(string $operation): Support
    {
        return match (true) {
            in_array($operation, $this->unsupportedOperations, true) => Support::None,
            in_array($operation, $this->fallbackOperations, true) => Support::PhpFallback,
            in_array($operation, $this->degradedOperations, true) => Support::Degraded,
            default => Support::Native,
        };
    }

    public function decode(ValidatedInput $input): ImageHandleInterface
    {
        $handle = $this->handle($input->header->canvas);
        $this->calls[] = sprintf('decode %s %s -> #%d', $input->header->format->value, self::size($input->header->canvas), $handle->id);

        return $handle;
    }

    public function create(Dimensions $dimensions, Color $background): ImageHandleInterface
    {
        $handle = $this->handle($dimensions);
        $this->calls[] = sprintf('create %s %s -> #%d', self::size($dimensions), $background->toHex(), $handle->id);

        return $handle;
    }

    public function copy(ImageHandleInterface $image): ImageHandleInterface
    {
        $source = $this->unwrap($image);
        $copy = $this->handle($source->dimensions);
        $this->calls[] = sprintf('copy #%d -> #%d', $source->id, $copy->id);

        return $copy;
    }

    public function apply(ImageHandleInterface $image, PrimitiveOperationInterface $operation): ImageHandleInterface
    {
        $source = $this->unwrap($image);
        $this->calls[] = sprintf('apply #%d %s', $source->id, self::describe($operation));

        return new FakeImageHandle($this->name, $operation->resultingDimensions($source->dimensions), $source->id);
    }

    public function encode(ImageHandleInterface $image, OutputFormatInterface $output): EncodedImage
    {
        $source = $this->unwrap($image);
        $this->calls[] = sprintf('encode #%d %s', $source->id, $output->format()->value);
        // PNG and GIF carry real headers so transfers and the GIF container writer work with fakes.
        $bytes = match ($output->format()) {
            FormatName::Png => ImageBytes::png($source->dimensions->width, $source->dimensions->height),
            FormatName::Gif => ImageBytes::gif($source->dimensions->width, $source->dimensions->height, [[$source->dimensions->width, $source->dimensions->height]]),
            default => str_pad(sprintf('fake-%s-%s', $output->format()->value, self::size($source->dimensions)), $this->encodedByteCounts[$output->format()->value] ?? 0, '.'),
        };

        return new EncodedImage($bytes, $output->format());
    }

    public function dimensions(ImageHandleInterface $image): Dimensions
    {
        return $this->unwrap($image)->dimensions;
    }

    public function colorAt(ImageHandleInterface $image, Point $point): Color
    {
        $this->unwrap($image);

        return $this->pixelColor;
    }

    public function readPixels(ImageHandleInterface $image, Rectangle $area): PixelBuffer
    {
        $source = $this->unwrap($image);
        $this->calls[] = sprintf('read #%d %s+%d+%d', $source->id, self::size($area->dimensions), $area->left(), $area->top());

        return PixelBuffer::filled($area->dimensions, $this->pixelColor);
    }

    public function measureText(string $text, Font $font): Rectangle
    {
        $this->calls[] = sprintf('measure "%s" %gpx', $text, $font->size);
        // A monospace approximation is enough for tests that only care about the calls made.
        $advance = (int) ceil($font->size * 0.6);

        return new Rectangle(new Point(0, -(int) ceil($font->size * 0.8)), new Dimensions(max(1, $advance * mb_strlen($text)), (int) ceil($font->size)));
    }

    public function writePixels(ImageHandleInterface $image, PixelBuffer $pixels, Point $origin): ImageHandleInterface
    {
        $target = $this->unwrap($image);
        $this->calls[] = sprintf('write #%d %s+%d+%d', $target->id, self::size($pixels->dimensions), $origin->x, $origin->y);

        return $target;
    }

    public static function describe(PrimitiveOperationInterface $operation): string
    {
        return match (true) {
            $operation instanceof Resize => 'resize ' . self::size($operation->target),
            $operation instanceof Crop => sprintf('crop %s+%d+%d', self::size($operation->area->dimensions), $operation->area->left(), $operation->area->top()),
            $operation instanceof Pad => sprintf('pad %d,%d,%d,%d %s', $operation->left, $operation->top, $operation->right, $operation->bottom, $operation->background->toHex()),
            $operation instanceof Trim => 'trim',
            $operation instanceof Rotate => sprintf('rotate %g %s', $operation->angle->clockwiseDegrees, $operation->background->toHex()),
            $operation instanceof Flip => 'flip ' . strtolower($operation->direction->name),
            $operation instanceof Grayscale => 'grayscale',
            $operation instanceof Composite => sprintf('composite %s+%d+%d opacity %g %s', self::size($operation->placement->dimensions), $operation->placement->left(), $operation->placement->top(), $operation->opacity, strtolower($operation->mode->name)),
            $operation instanceof Draw => 'draw ' . implode(', ', array_map(static fn(object $shape): string => (new \ReflectionClass($shape))->getShortName(), $operation->shapes)),
            $operation instanceof Invert => 'invert',
            $operation instanceof Brightness => 'brightness ' . $operation->level,
            $operation instanceof Contrast => 'contrast ' . $operation->level,
            $operation instanceof Gamma => sprintf('gamma %g', $operation->gamma),
            $operation instanceof Blur => sprintf('blur %g', $operation->sigma),
            $operation instanceof Sharpen => sprintf('sharpen %g', $operation->amount),
            $operation instanceof Colorize => sprintf('colorize %s %g', $operation->tint->toHex(), $operation->strength),
            default => $operation::class,
        };
    }

    private function handle(Dimensions $dimensions): FakeImageHandle
    {
        return new FakeImageHandle($this->name, $dimensions, $this->nextHandleId++);
    }

    private function unwrap(ImageHandleInterface $image): FakeImageHandle
    {
        if (!$image instanceof FakeImageHandle || $image->driver() !== $this->name) {
            throw IncompatibleHandleException::belongsTo($image->driver(), $this->name);
        }

        return $image;
    }

    private static function size(Dimensions $dimensions): string
    {
        return $dimensions->width . 'x' . $dimensions->height;
    }
}
