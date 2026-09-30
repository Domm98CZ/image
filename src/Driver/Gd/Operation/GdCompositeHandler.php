<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Domm98CZ\Image\Blend\BlendMode;
use Domm98CZ\Image\Blend\Compositor;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Driver\Gd\Format\GdCodec;
use Domm98CZ\Image\Driver\Gd\GdCanvas;
use Domm98CZ\Image\Driver\Gd\GdImageHandle;
use Domm98CZ\Image\Driver\Gd\GdPixels;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\Operation\Composite;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use GdImage;

/** @implements GdOperationHandlerInterface<Composite> */
final readonly class GdCompositeHandler implements GdOperationHandlerInterface
{
    public function operation(): string
    {
        return Composite::class;
    }

    // Normal blending is native; other modes run the PHP compositor, but only over the overlay's area.
    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        $imageArea = Rectangle::covering(GdCanvas::dimensions($image));
        $visible = $operation->placement->intersection($imageArea);
        if ($visible === null || $operation->opacity === 0.0) {
            return $image;
        }
        $overlay = self::overlay($operation->overlay, $operation->placement);
        $overlayArea = new Rectangle(
            new Point($visible->left() - $operation->placement->left(), $visible->top() - $operation->placement->top()),
            $visible->dimensions,
        );

        if ($operation->mode === BlendMode::Normal) {
            if ($operation->opacity < 1.0) {
                GdPixels::write($overlay, self::fadedAlpha(GdPixels::read($overlay, $overlayArea), $operation->opacity), $overlayArea->origin);
            }
            imagealphablending($image, true);
            imagecopy($image, $overlay, $visible->left(), $visible->top(), $overlayArea->left(), $overlayArea->top(), $visible->dimensions->width, $visible->dimensions->height);
            imagealphablending($image, false);

            return $image;
        }

        $blended = (new Compositor())->blend(GdPixels::read($image, $visible), GdPixels::read($overlay, $overlayArea), $operation->mode, $operation->opacity);
        GdPixels::write($image, $blended, $visible->origin);

        return $image;
    }

    // A private, correctly sized GD copy of the overlay, whichever driver holds it.
    private static function overlay(Image $overlay, Rectangle $placement): GdImage
    {
        $handle = $overlay->handle();
        $source = $handle instanceof GdImageHandle
            ? $handle->image
            : GdCodec::decode(FormatName::Png, $overlay->encode(new PngOutput(1, keepAlphaChannel: true))->bytes);
        GdCanvas::prepare($source);
        $copy = GdCanvas::blank($placement->dimensions, Color::transparent());
        imagecopyresampled($copy, $source, 0, 0, 0, 0, $placement->dimensions->width, $placement->dimensions->height, imagesx($source), imagesy($source));

        return $copy;
    }

    private static function fadedAlpha(PixelBuffer $pixels, float $opacity): PixelBuffer
    {
        $bytes = $pixels->bytes;
        for ($offset = 3, $length = strlen($bytes); $offset < $length; $offset += 4) {
            $bytes[$offset] = chr((int) round(ord($bytes[$offset]) * $opacity));
        }

        return $pixels->withBytes($bytes);
    }
}
