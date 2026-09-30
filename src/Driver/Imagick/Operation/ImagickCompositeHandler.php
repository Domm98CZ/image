<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Blend\BlendMode;
use Domm98CZ\Image\Blend\Compositor;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Driver\Imagick\ImagickImageHandle;
use Domm98CZ\Image\Driver\Imagick\ImagickPixels;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\Operation\Composite;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;
use ImagickPixel;

/** @implements ImagickOperationHandlerInterface<Composite> */
final class ImagickCompositeHandler implements ImagickOperationHandlerInterface
{
    // Backdrop/source RGBA pairs that exercise alpha: ImageMagick 7 disagrees with W3C for some modes here.
    private const PROBES = [
        [[200, 100, 50, 255], [60, 180, 240, 255]],
        [[200, 100, 50, 255], [60, 180, 240, 128]],
        [[30, 220, 120, 128], [250, 20, 130, 200]],
    ];
    // Per-channel rounding differences between Q16 arithmetic and the 8-bit reference compositor.
    private const PROBE_TOLERANCE = 2;

    /** @var array<string, bool> native operator matches the documented formula, per BlendMode name */
    private array $nativeMatches = [];

    public function operation(): string
    {
        return Composite::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        $overlay = self::overlay($operation->overlay, $operation->placement->dimensions);
        if (!$overlay->getImageAlphaChannel()) {
            $overlay->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
        }

        if ($this->nativeMatches($operation->mode)) {
            if ($operation->opacity < 1.0) {
                $overlay->evaluateImage(Imagick::EVALUATE_MULTIPLY, $operation->opacity, Imagick::CHANNEL_ALPHA);
            }
            $image->compositeImage($overlay, self::operator($operation->mode), $operation->placement->left(), $operation->placement->top());

            return $image;
        }

        $visible = $operation->placement->intersection(new Rectangle(Point::origin(), new Dimensions($image->getImageWidth(), $image->getImageHeight())));
        if ($visible === null) {
            return $image;
        }
        $overlayArea = new Rectangle(new Point($visible->left() - $operation->placement->left(), $visible->top() - $operation->placement->top()), $visible->dimensions);
        $blended = (new Compositor())->blend(ImagickPixels::read($image, $visible), ImagickPixels::read($overlay, $overlayArea), $operation->mode, $operation->opacity);
        ImagickPixels::write($image, $blended, $visible->origin);

        return $image;
    }

    private function nativeMatches(BlendMode $mode): bool
    {
        return $this->nativeMatches[$mode->name] ??= self::probe($mode);
    }

    private static function probe(BlendMode $mode): bool
    {
        foreach (self::PROBES as [$backdrop, $source]) {
            $expected = (new Compositor())->blend(new PixelBuffer(new Dimensions(1, 1), pack('C4', ...$backdrop)), new PixelBuffer(new Dimensions(1, 1), pack('C4', ...$source)), $mode);
            $canvas = self::pixel($backdrop);
            $canvas->compositeImage(self::pixel($source), self::operator($mode), 0, 0);
            $actual = ImagickPixels::read($canvas, new Rectangle(Point::origin(), new Dimensions(1, 1)));
            foreach (range(0, 3) as $channel) {
                if (abs(ord($expected->bytes[$channel]) - ord($actual->bytes[$channel])) > self::PROBE_TOLERANCE) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array{int, int, int, int} $rgba */
    private static function pixel(array $rgba): Imagick
    {
        $pixel = new Imagick();
        $pixel->newImage(1, 1, new ImagickPixel(sprintf('rgba(%d, %d, %d, %F)', $rgba[0], $rgba[1], $rgba[2], $rgba[3] / 255)));

        return $pixel;
    }

    private static function overlay(Image $overlay, Dimensions $size): Imagick
    {
        $handle = $overlay->handle();
        if ($handle instanceof ImagickImageHandle) {
            $copy = clone $handle->image;
        } else {
            $copy = new Imagick();
            $copy->readImageBlob($overlay->encode(new PngOutput(1, keepAlphaChannel: true))->bytes);
        }
        if ($copy->getImageWidth() !== $size->width || $copy->getImageHeight() !== $size->height) {
            $copy->resizeImage($size->width, $size->height, Imagick::FILTER_LANCZOS, 1.0);
        }
        $copy->setImagePage(0, 0, 0, 0);

        return $copy;
    }

    /** @return Imagick::COMPOSITE_* */
    private static function operator(BlendMode $mode): int
    {
        return match ($mode) {
            BlendMode::Normal => Imagick::COMPOSITE_OVER,
            BlendMode::Multiply => Imagick::COMPOSITE_MULTIPLY,
            BlendMode::Screen => Imagick::COMPOSITE_SCREEN,
            BlendMode::Overlay => Imagick::COMPOSITE_OVERLAY,
            BlendMode::Darken => Imagick::COMPOSITE_DARKEN,
            BlendMode::Lighten => Imagick::COMPOSITE_LIGHTEN,
            BlendMode::Difference => Imagick::COMPOSITE_DIFFERENCE,
            BlendMode::HardLight => Imagick::COMPOSITE_HARDLIGHT,
            BlendMode::SoftLight => Imagick::COMPOSITE_SOFTLIGHT,
            BlendMode::ColorDodge => Imagick::COMPOSITE_COLORDODGE,
            BlendMode::ColorBurn => Imagick::COMPOSITE_COLORBURN,
            BlendMode::Exclusion => Imagick::COMPOSITE_EXCLUSION,
        };
    }
}
