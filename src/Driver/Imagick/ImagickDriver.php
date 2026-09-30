<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Color\Profile\RgbMatrixProfile;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\FontCoverage;
use Domm98CZ\Image\Drawing\Text;
use Domm98CZ\Image\Drawing\TextLayout;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\ImageHandleInterface;
use Domm98CZ\Image\Driver\Imagick\Format\ImagickAvifFormat;
use Domm98CZ\Image\Driver\Imagick\Format\ImagickCodec;
use Domm98CZ\Image\Driver\Imagick\Format\ImagickFormatInterface;
use Domm98CZ\Image\Driver\Imagick\Format\ImagickGifFormat;
use Domm98CZ\Image\Driver\Imagick\Format\ImagickHeicFormat;
use Domm98CZ\Image\Driver\Imagick\Format\ImagickJpegFormat;
use Domm98CZ\Image\Driver\Imagick\Format\ImagickPngFormat;
use Domm98CZ\Image\Driver\Imagick\Format\ImagickWebpFormat;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickBlurHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickBrightnessHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickColorizeHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickCompositeHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickContrastHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickCropHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickDrawHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickFlipHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickGammaHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickGrayscaleHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickInvertHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickOperationHandlerInterface;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickPadHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickResizeHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickRotateHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickSharpenHandler;
use Domm98CZ\Image\Driver\Imagick\Operation\ImagickTrimHandler;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\IncompatibleHandleException;
use Domm98CZ\Image\Exception\InvalidInputException;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Exception\UnsupportedOperationException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Output\AvifOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Operation\Draw;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Security\ValidatedInput;
use Imagick;
use ImagickDraw;
use ImagickException;
use ImagickPixel;
use ImagickPixelException;

final readonly class ImagickDriver implements DriverInterface
{
    // A linear-transfer gray 128 converted to sRGB by a working lcms lands here.
    private const SRGB_GRAY_FROM_LINEAR_128 = 188;
    private const GRAY_PROBE_TOLERANCE = 4;
    // Correctly decoded RED_AVIF_SAMPLE pixels are saturated red; the HEIC-based reader lands far from it.
    private const AVIF_PROBE_MIN_RED = 200;
    private const AVIF_PROBE_MAX_OTHER = 60;

    // 8x8 solid red, encoded by libavif; decoding it shows whether this ImageMagick build reads AVIF correctly.
    private const RED_AVIF_SAMPLE = 'AAAAIGZ0eXBhdmlmAAAAAGF2aWZtaWYxbWlhZk1BMUEAAADrbWV0YQAAAAAAAAAhaGRscgAAAAAAAAAAcGljdAAAAAAAAAAAAAAAAAAAAAAOcGl0bQAAAAAAAQAAAB5pbG9jAAAAAEQAAAEAAQAAAAEAAAETAAAAIAAAAChpaW5mAAAAAAABAAAAGmluZmUCAAAAAAEAAGF2MDFDb2xvcgAAAABqaXBycAAAAEtpcGNvAAAAFGlzcGUAAAAAAAAACAAAAAgAAAAQcGl4aQAAAAADCAgIAAAADGF2MUOBIAAAAAAAE2NvbHJuY2x4AAEADQABgAAAABdpcG1hAAAAAAAAAAEAAQQBAoMEAAAAKG1kYXQSAAoIOAi/YQENAZAyEhGAAABAAGjTBXOYsJ37rsS+MA==';

    // Two 4x4 frames (red 100 ms, lime 200 ms, loop forever), lossless, written by libwebp's animation API.
    private const ANIMATED_WEBP_SAMPLE = 'UklGRoQAAABXRUJQVlA4WAoAAAACAAAAAwAAAwAAQU5JTQYAAAD/////AABBTk1GKAAAAAAAAAAAAAMAAAMAAGQAAAJWUDhMDwAAAC8DwAAABxD9j/4HIqL/AQBBTk1GKAAAAAAAAAAAAAMAAAMAAMgAAABWUDhMDwAAAC8DwAAAB9D/iP4HIqL/AQA=';

    /** @var array<class-string<PrimitiveOperationInterface>, ImagickOperationHandlerInterface<PrimitiveOperationInterface>> */
    private array $handlers;

    private ImagickCapabilities $capabilities;

    /** @param list<ImagickOperationHandlerInterface<PrimitiveOperationInterface>> $extraHandlers */
    public function __construct(Limits $limits = new Limits(), array $extraHandlers = [])
    {
        self::applyResourceLimits($limits);
        $this->capabilities = new ImagickCapabilities();

        $handlers = [];
        foreach ([new ImagickResizeHandler(), new ImagickCropHandler(), new ImagickPadHandler(), new ImagickTrimHandler(), new ImagickRotateHandler(), new ImagickFlipHandler(), new ImagickGrayscaleHandler(), new ImagickInvertHandler(), new ImagickBrightnessHandler(), new ImagickContrastHandler(), new ImagickGammaHandler(), new ImagickBlurHandler(), new ImagickSharpenHandler(), new ImagickColorizeHandler(), new ImagickCompositeHandler(), new ImagickDrawHandler(), ...$extraHandlers] as $handler) {
            /** @var ImagickOperationHandlerInterface<PrimitiveOperationInterface> $handler */
            $handlers[$handler->operation()] = $handler;
        }
        $this->handlers = $handlers;
    }

    public static function isAvailable(): bool
    {
        return extension_loaded('imagick');
    }

    public function name(): DriverName
    {
        return DriverName::Imagick;
    }

    public function decodingSupport(FormatName $format): Support
    {
        if (!ImagickCodec::isSupported($format)) {
            return Support::None;
        }

        return $format === FormatName::Avif ? $this->capabilities->avifDecoding(self::probeAvifDecoding(...)) : Support::Native;
    }

    public function encodingSupport(OutputFormatInterface $output): Support
    {
        if (!self::formatFor($output->format())->isSupported()) {
            return Support::None;
        }

        return $output instanceof AvifOutput ? $this->capabilities->avifEncoding(self::probeAvifEncoding(...)) : Support::Native;
    }

    public function pixelAccess(): Support
    {
        return Support::Native;
    }

    public function colorManagement(): Support
    {
        return $this->capabilities->colorManagement(self::probeColorManagement(...));
    }

    public function animatedWebpDecoding(): Support
    {
        return $this->capabilities->animatedWebpDecoding(self::probeAnimatedWebpDecoding(...));
    }

    public function animatedWebpEncoding(): Support
    {
        return $this->capabilities->animatedWebpEncoding(self::probeAnimatedWebpEncoding(...));
    }

    public function support(string $operation): Support
    {
        return array_key_exists($operation, $this->handlers) ? $this->handlers[$operation]->support() : Support::None;
    }

    public function decode(ValidatedInput $input): ImageHandleInterface
    {
        $format = $input->header->format;
        if (!$this->decodingSupport($format)->isSupported()) {
            throw UnsupportedFormatException::cannotDecode($format, DriverName::Imagick);
        }
        $image = ImagickCodec::decode($format, $input->bytes);

        return new ImagickImageHandle(ImagickCall::run('prepare the decoded image', static function () use ($image): Imagick {
            // A still image is one frame; multi-frame input goes through Animation\ codecs, so only the first (coalesced) frame is kept here.
            if ($image->getNumberImages() > 1) {
                $image = $image->coalesceImages();
                $image->setIteratorIndex(0);
                $image = $image->getImage();
            }
            ImagickColorProfile::toSrgb($image);
            $image->setImagePage(0, 0, 0, 0);

            return $image;
        }));
    }

    public function create(Dimensions $dimensions, Color $background): ImageHandleInterface
    {
        return new ImagickImageHandle(ImagickCall::run('create a canvas', static function () use ($dimensions, $background): Imagick {
            $image = new Imagick();
            $image->newImage($dimensions->width, $dimensions->height, ImagickColor::toPixel($background));

            return $image;
        }));
    }

    public function copy(ImageHandleInterface $image): ImageHandleInterface
    {
        return new ImagickImageHandle(clone self::unwrap($image));
    }

    public function apply(ImageHandleInterface $image, PrimitiveOperationInterface $operation): ImageHandleInterface
    {
        $handler = $this->handlers[$operation::class] ?? throw UnsupportedOperationException::noDriverSupports($operation::class, [DriverName::Imagick]);
        $imagick = self::unwrap($image);

        return new ImagickImageHandle(ImagickCall::run('apply ' . $operation::class, static fn(): Imagick => $handler->apply($imagick, $operation)));
    }

    public function encode(ImageHandleInterface $image, OutputFormatInterface $output): EncodedImage
    {
        $format = $output->format();
        if (!$this->encodingSupport($output)->isSupported()) {
            throw UnsupportedFormatException::cannotEncode($format, DriverName::Imagick);
        }
        $copy = clone self::unwrap($image);
        ImagickCall::run('strip metadata', static function () use ($copy): void {
            // Same output as GD, which keeps no metadata: no stale EXIF orientation after autoOrient().
            $copy->stripImage();
            $copy->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        });

        return new EncodedImage(self::formatFor($format)->encode($copy, $output), $format);
    }

    public function dimensions(ImageHandleInterface $image): Dimensions
    {
        $imagick = self::unwrap($image);

        return new Dimensions($imagick->getImageWidth(), $imagick->getImageHeight());
    }

    public function colorAt(ImageHandleInterface $image, Point $point): Color
    {
        $imagick = self::unwrap($image);
        $this->dimensions($image)->assertContainsPoint($point);

        return ImagickColor::fromPixel(ImagickCall::run('read a pixel', static fn() => $imagick->getImagePixelColor($point->x, $point->y)));
    }

    public function readPixels(ImageHandleInterface $image, Rectangle $area): PixelBuffer
    {
        $imagick = self::unwrap($image);
        $area->assertWithin($this->dimensions($image));

        return ImagickPixels::read($imagick, $area);
    }

    // Font metrics describe the whole font (tallest ascender, deepest descender); rendering with the Draw handler on a
    // spare canvas and trimming gives the ink box GD reports, with the lines at the library's pitch rather than ImageMagick's.
    public function measureText(string $text, Font $font): Rectangle
    {
        $text = FontCoverage::of($font)->substitute($text);
        $handler = $this->handlers[Draw::class] ?? throw UnsupportedOperationException::noDriverSupports(Draw::class, [DriverName::Imagick]);

        return ImagickCall::run('measure text', static function () use ($text, $font, $handler): Rectangle {
            $draw = new ImagickDraw();
            $draw->setFont($font->path);
            $draw->setFontSize($font->size);
            $lines = TextLayout::lines($text);
            $probe = new Imagick();
            [$textWidth, $lineHeight, $ascender] = [0.0, 0.0, 0.0];
            foreach ($lines as $line) {
                $metrics = $probe->queryFontMetrics($draw, $line, false);
                $textWidth = max($textWidth, $metrics['textWidth']);
                $lineHeight = $metrics['textHeight'];
                $ascender = $metrics['ascender'];
            }
            $ascender = (int) ceil($ascender);
            // Advance box of the stacked lines: the last baseline sits (n - 1) pitches below the first.
            $lastBaseline = TextLayout::baseline(Point::origin(), Angle::clockwise(0), TextLayout::pitch($font), count($lines) - 1)->y;
            $advance = new Dimensions(max(1, (int) ceil($textWidth)), max(1, $lastBaseline + (int) ceil($lineHeight)));
            $margin = (int) ceil($font->size);
            $width = $advance->width + 2 * $margin;
            $height = $advance->height + 2 * $margin;

            $canvas = new Imagick();
            $canvas->newImage($width, $height, new ImagickPixel('transparent'));
            $handler->apply($canvas, new Draw([new Text($text, new Point($margin, $margin + $ascender), $font, Color::black())]));
            $canvas->trimImage(0);
            $page = $canvas->getImagePage();
            // ImageMagick 7 trims a fully transparent canvas to 1x1 at page offset -1,-1; older builds leave it untrimmed.
            $nothingVisible = $page['x'] < 0 || $page['y'] < 0 || ($canvas->getImageWidth() >= $width && $canvas->getImageHeight() >= $height);
            if ($nothingVisible) {
                // Only spaces: fall back to the advance box.
                return new Rectangle(new Point(0, -$ascender), $advance);
            }

            return new Rectangle(
                new Point($page['x'] - $margin, $page['y'] - $margin - $ascender),
                new Dimensions($canvas->getImageWidth(), $canvas->getImageHeight()),
            );
        });
    }

    public function writePixels(ImageHandleInterface $image, PixelBuffer $pixels, Point $origin): ImageHandleInterface
    {
        $imagick = self::unwrap($image);
        (new Rectangle($origin, $pixels->dimensions))->assertWithin($this->dimensions($image));

        ImagickPixels::write($imagick, $pixels, $origin);

        return $image;
    }

    private static function probeAvifEncoding(): Support
    {
        try {
            $probe = new Imagick();
            $probe->newImage(2, 2, ImagickColor::toPixel(new Color(255, 0, 0, 128)));
            $probe->setImageFormat(ImagickCodec::coder(FormatName::Avif));
            $header = (new HeaderProbe())->probe(new BinaryString($probe->getImageBlob()));
        } catch (ImagickException|InvalidInputException) {
            return Support::None;
        }

        // Some ImageMagick builds (e.g. Debian bookworm) write AVIF but silently drop alpha.
        return $header->hasAlpha ? Support::Native : Support::Degraded;
    }

    // ImageMagick 6 (e.g. Debian bookworm) reads animated WebP with shuffled frame delays.
    private static function probeAnimatedWebpDecoding(): Support
    {
        try {
            $probe = new Imagick();
            $probe->readImageBlob((string) base64_decode(self::ANIMATED_WEBP_SAMPLE, true));
            $probe = $probe->coalesceImages();
            $delays = [];
            foreach ($probe as $frame) {
                $delays[] = $frame->getImageDelay();
            }
        } catch (ImagickException) {
            return Support::None;
        }

        return $delays === [10, 20] ? Support::Native : Support::None;
    }

    // ImageMagick 6 (e.g. Debian bookworm) writes animated WebP with a wrong loop count and frame durations.
    private static function probeAnimatedWebpEncoding(): Support
    {
        try {
            $probe = new Imagick();
            foreach (['red' => 10, 'lime' => 20] as $color => $delay) {
                $frame = new Imagick();
                $frame->newImage(4, 4, new ImagickPixel($color));
                $frame->setImageFormat(ImagickCodec::coder(FormatName::Webp));
                $frame->setImageTicksPerSecond(100);
                $frame->setImageDelay($delay);
                $probe->addImage($frame);
            }
            $probe->setIteratorIndex(0);
            $probe->setImageIterations(3);
            $written = new BinaryString($probe->getImagesBlob());
            $animation = self::webpAnimationChunks($written);
        } catch (ImagickException|InvalidInputException) {
            return Support::None;
        }

        return $animation === [3, [100, 200]] ? Support::Native : Support::None;
    }

    /** @return array{int, list<int>}|null loop count and frame durations (ms) read straight from the RIFF chunks */
    private static function webpAnimationChunks(BinaryString $webp): ?array
    {
        $loop = null;
        $durations = [];
        for ($offset = 12; $webp->has($offset, 8); $offset += 8 + $size + ($size & 1)) {
            $size = $webp->uint32LittleEndian($offset + 4);
            if ($webp->matchesAt($offset, 'ANIM')) {
                $loop = $webp->uint16LittleEndian($offset + 12);
            } elseif ($webp->matchesAt($offset, 'ANMF')) {
                $durations[] = $webp->uint24LittleEndian($offset + 20);
            }
        }

        return $loop === null ? null : [$loop, $durations];
    }

    // Needs ImageMagick built with lcms: a linear-transfer gray 128 must come out near sRGB 188.
    private static function probeColorManagement(): Support
    {
        try {
            $probe = new Imagick();
            $probe->newImage(1, 1, new ImagickPixel('rgb(128,128,128)'));
            $probe->setImageProfile('icc', RgbMatrixProfile::linearSrgbPrimaries()->bytes());
            ImagickColorProfile::toSrgb($probe);
            $gray = ImagickColor::fromPixel($probe->getImagePixelColor(0, 0));
        } catch (ImagickException|ImagickPixelException) {
            return Support::Degraded;
        }

        return abs($gray->green - self::SRGB_GRAY_FROM_LINEAR_128) <= self::GRAY_PROBE_TOLERANCE ? Support::Native : Support::Degraded;
    }

    private static function probeAvifDecoding(): Support
    {
        try {
            $probe = new Imagick();
            $probe->readImageBlob((string) base64_decode(self::RED_AVIF_SAMPLE, true));
            $red = ImagickColor::fromPixel($probe->getImagePixelColor(4, 4));
        } catch (ImagickException|ImagickPixelException) {
            return Support::None;
        }

        // ImageMagick 6's HEIC-based AVIF reader (e.g. Debian bookworm) decodes with wrong colours.
        return $red->red > self::AVIF_PROBE_MIN_RED && $red->green < self::AVIF_PROBE_MAX_OTHER && $red->blue < self::AVIF_PROBE_MAX_OTHER ? Support::Native : Support::Degraded;
    }

    // ImageMagick resource limits are process-global; they back up InputGuard, which rejects oversize input first.
    private static function applyResourceLimits(Limits $limits): void
    {
        $resources = [
            'RESOURCETYPE_WIDTH' => $limits->maxWidth,
            'RESOURCETYPE_HEIGHT' => $limits->maxHeight,
            'RESOURCETYPE_LISTLENGTH' => $limits->maxFrames,
        ];
        foreach ($resources as $constant => $value) {
            if (defined(Imagick::class . '::' . $constant)) {
                /** @var int $type */
                $type = constant(Imagick::class . '::' . $constant);
                Imagick::setResourceLimit($type, $value);
            }
        }
    }

    private static function formatFor(FormatName $format): ImagickFormatInterface
    {
        return match ($format) {
            FormatName::Jpeg => new ImagickJpegFormat(),
            FormatName::Png => new ImagickPngFormat(),
            FormatName::Gif => new ImagickGifFormat(),
            FormatName::Webp => new ImagickWebpFormat(),
            FormatName::Avif => new ImagickAvifFormat(),
            FormatName::Heic => new ImagickHeicFormat(),
        };
    }

    private static function unwrap(ImageHandleInterface $image): Imagick
    {
        if (!$image instanceof ImagickImageHandle) {
            throw IncompatibleHandleException::belongsTo($image->driver(), DriverName::Imagick);
        }

        return $image->image;
    }
}
