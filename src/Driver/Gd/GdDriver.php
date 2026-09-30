<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\Text;
use Domm98CZ\Image\Drawing\TextLayout;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\Gd\Format\GdAvifFormat;
use Domm98CZ\Image\Driver\Gd\Format\GdFormatInterface;
use Domm98CZ\Image\Driver\Gd\Format\GdGifFormat;
use Domm98CZ\Image\Driver\Gd\Format\GdHeicFormat;
use Domm98CZ\Image\Driver\Gd\Format\GdJpegFormat;
use Domm98CZ\Image\Driver\Gd\Format\GdPngFormat;
use Domm98CZ\Image\Driver\Gd\Format\GdWebpFormat;
use Domm98CZ\Image\Driver\Gd\Operation\GdBlurHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdBrightnessHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdColorizeHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdCompositeHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdContrastHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdCropHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdDrawHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdFlipHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdGammaHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdGrayscaleHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdInvertHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdOperationHandlerInterface;
use Domm98CZ\Image\Driver\Gd\Operation\GdPadHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdResizeHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdRotateHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdSharpenHandler;
use Domm98CZ\Image\Driver\Gd\Operation\GdTrimHandler;
use Domm98CZ\Image\Driver\ImageHandleInterface;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\DriverOperationFailedException;
use Domm98CZ\Image\Exception\IncompatibleHandleException;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Exception\UnsupportedOperationException;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Operation\Draw;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Security\ValidatedInput;
use GdImage;

final readonly class GdDriver implements DriverInterface
{
    // Ink strays at most a few pixels outside imagettfbbox (measured up to 4 px at 160 px); the margin grows with the size.
    private const MEASURE_MARGIN = 2;
    private const MEASURE_MARGIN_DIVISOR = 8;

    /** @var array<class-string<PrimitiveOperationInterface>, GdOperationHandlerInterface<PrimitiveOperationInterface>> */
    private array $handlers;

    /** @param list<GdOperationHandlerInterface<PrimitiveOperationInterface>> $extraHandlers */
    public function __construct(array $extraHandlers = [], private Limits $limits = new Limits())
    {
        $handlers = [];
        foreach ([new GdResizeHandler(), new GdCropHandler(), new GdPadHandler(), new GdTrimHandler(), new GdRotateHandler(), new GdFlipHandler(), new GdGrayscaleHandler(), new GdInvertHandler(), new GdBrightnessHandler(), new GdContrastHandler(), new GdGammaHandler(), new GdBlurHandler(), new GdSharpenHandler(), new GdColorizeHandler(), new GdCompositeHandler(), new GdDrawHandler(), ...$extraHandlers] as $handler) {
            /** @var GdOperationHandlerInterface<PrimitiveOperationInterface> $handler */
            $handlers[$handler->operation()] = $handler;
        }
        $this->handlers = $handlers;
    }

    public static function isAvailable(): bool
    {
        return extension_loaded('gd');
    }

    public function name(): DriverName
    {
        return DriverName::Gd;
    }

    public function decodingSupport(FormatName $format): Support
    {
        return self::formatFor($format)->isSupported() ? Support::Native : Support::None;
    }

    public function encodingSupport(OutputFormatInterface $output): Support
    {
        return self::formatFor($output->format())->isSupported() ? Support::Native : Support::None;
    }

    // GD has no bulk pixel export: buffer steps cost a per-pixel PHP loop (~1.1 s read per 12 MP).
    public function pixelAccess(): Support
    {
        return Support::PhpFallback;
    }

    // GD ignores embedded profiles: the numbers are kept but read as sRGB, so wide-gamut colors come out muted.
    public function colorManagement(): Support
    {
        return Support::Degraded;
    }

    public function support(string $operation): Support
    {
        return array_key_exists($operation, $this->handlers) ? $this->handlers[$operation]->support() : Support::None;
    }

    public function decode(ValidatedInput $input): ImageHandleInterface
    {
        $format = $input->header->format;
        if (!$this->decodingSupport($format)->isSupported()) {
            throw UnsupportedFormatException::cannotDecode($format, DriverName::Gd);
        }
        $image = self::formatFor($format)->decode($input->bytes);
        GdCanvas::prepare($image);

        return new GdImageHandle($image);
    }

    public function create(Dimensions $dimensions, Color $background): ImageHandleInterface
    {
        return new GdImageHandle(GdCanvas::blank($dimensions, $background));
    }

    public function copy(ImageHandleInterface $image): ImageHandleInterface
    {
        $source = self::unwrap($image);
        $copy = GdCanvas::blank(GdCanvas::dimensions($source), Color::transparent());
        imagecopy($copy, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));

        return new GdImageHandle($copy);
    }

    public function apply(ImageHandleInterface $image, PrimitiveOperationInterface $operation): ImageHandleInterface
    {
        $handler = $this->handlers[$operation::class] ?? throw UnsupportedOperationException::noDriverSupports($operation::class, [DriverName::Gd]);

        return new GdImageHandle($handler->apply(self::unwrap($image), $operation));
    }

    public function encode(ImageHandleInterface $image, OutputFormatInterface $output): EncodedImage
    {
        $format = $output->format();
        if (!$this->encodingSupport($output)->isSupported()) {
            throw UnsupportedFormatException::cannotEncode($format, DriverName::Gd);
        }

        return new EncodedImage(self::formatFor($format)->encode(self::unwrap($image), $output), $format);
    }

    public function dimensions(ImageHandleInterface $image): Dimensions
    {
        return GdCanvas::dimensions(self::unwrap($image));
    }

    public function colorAt(ImageHandleInterface $image, Point $point): Color
    {
        $gdImage = self::unwrap($image);
        GdCanvas::dimensions($gdImage)->assertContainsPoint($point);

        return GdColor::fromTruecolor((int) imagecolorat($gdImage, $point->x, $point->y));
    }

    public function readPixels(ImageHandleInterface $image, Rectangle $area): PixelBuffer
    {
        $gdImage = self::unwrap($image);
        $area->assertWithin(GdCanvas::dimensions($gdImage));

        return GdPixels::read($gdImage, $area);
    }

    // FreeType's box (imagettfbbox) misses the rendered ink by a pixel or two, so the text is rendered on a spare
    // canvas and trimmed, exactly as the Draw handler will paint it; the FreeType box only sizes that canvas.
    public function measureText(string $text, Font $font): Rectangle
    {
        $predicted = self::predictTextBox($text, $font);
        if ($text === '') {
            return $predicted;
        }
        $margin = self::MEASURE_MARGIN + (int) ceil($font->size / self::MEASURE_MARGIN_DIVISOR);
        while (true) {
            $canvas = new Dimensions($predicted->dimensions->width + 2 * $margin, $predicted->dimensions->height + 2 * $margin);
            if (!$this->withinLimits($canvas)) {
                return $predicted;
            }
            $baseline = new Point($margin - $predicted->left(), $margin - $predicted->top());
            $image = GdCanvas::blank($canvas, Color::transparent());
            $background = GdColor::allocate($image, Color::transparent());
            $handler = $this->handlers[Draw::class] ?? throw UnsupportedOperationException::noDriverSupports(Draw::class, [DriverName::Gd]);
            $handler->apply($image, new Draw([new Text($text, $baseline, $font, Color::black())]));
            $ink = GdInk::box($image, $background);
            if ($ink === null) {
                // Nothing visible (e.g. only spaces): the advance box is all there is.
                return $predicted;
            }
            if (!GdInk::touchesEdge($ink, $canvas) || $margin >= $font->size) {
                return new Rectangle(new Point($ink->left() - $baseline->x, $ink->top() - $baseline->y), $ink->dimensions);
            }
            $margin *= 2;
        }
    }

    // Union of the lines' FreeType boxes, each at its baseline of the library's pitch (libgd's own "\n" pitch differs).
    private static function predictTextBox(string $text, Font $font): Rectangle
    {
        $pitch = TextLayout::pitch($font);
        [$left, $top, $right, $bottom] = [PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MIN, PHP_INT_MIN];
        foreach (self::measurableLines($text) as $index => $line) {
            $drawn = GdText::encode($line, $font);
            $call = CapturedCall::run(static fn(): array|false => imagettfbbox($font->size * GdDrawHandler::POINTS_PER_PIXEL, 0, $font->path, $drawn));
            if ($call->result === false) {
                throw DriverOperationFailedException::because(DriverName::Gd, 'measure text', $call->error);
            }
            // Corners: lower-left, lower-right, upper-right, upper-left, each as x, y.
            $corners = array_map(static fn(mixed $value): int => is_numeric($value) ? (int) $value : 0, array_values($call->result));
            $baseline = TextLayout::baseline(Point::origin(), Angle::clockwise(0), $pitch, $index);
            foreach ([0, 2, 4, 6] as $corner) {
                $x = ($corners[$corner] ?? 0) + $baseline->x;
                $y = ($corners[$corner + 1] ?? 0) + $baseline->y;
                [$left, $top, $right, $bottom] = [min($left, $x), min($top, $y), max($right, $x), max($bottom, $y)];
            }
        }

        return new Rectangle(new Point($left, $top), new Dimensions(max(1, $right - $left), max(1, $bottom - $top)));
    }

    /** @return array<int, string> line index => line; blank lines take no room of their own unless nothing else is there */
    private static function measurableLines(string $text): array
    {
        $lines = array_filter(TextLayout::lines($text), static fn(string $line): bool => $line !== '');

        return $lines === [] ? [''] : $lines;
    }

    private function withinLimits(Dimensions $canvas): bool
    {
        return $canvas->width <= $this->limits->maxWidth && $canvas->height <= $this->limits->maxHeight && $canvas->pixelCount() <= $this->limits->maxPixels;
    }

    public function writePixels(ImageHandleInterface $image, PixelBuffer $pixels, Point $origin): ImageHandleInterface
    {
        $target = self::unwrap($image);
        (new Rectangle($origin, $pixels->dimensions))->assertWithin(GdCanvas::dimensions($target));

        GdPixels::write($target, $pixels, $origin);

        return $image;
    }

    private static function formatFor(FormatName $format): GdFormatInterface
    {
        return match ($format) {
            FormatName::Jpeg => new GdJpegFormat(),
            FormatName::Png => new GdPngFormat(),
            FormatName::Gif => new GdGifFormat(),
            FormatName::Webp => new GdWebpFormat(),
            FormatName::Avif => new GdAvifFormat(),
            FormatName::Heic => new GdHeicFormat(),
        };
    }

    private static function unwrap(ImageHandleInterface $image): GdImage
    {
        if (!$image instanceof GdImageHandle) {
            throw IncompatibleHandleException::belongsTo($image->driver(), DriverName::Gd);
        }

        return $image->image;
    }
}
