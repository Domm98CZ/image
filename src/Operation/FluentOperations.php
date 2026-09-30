<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Closure;
use Domm98CZ\Image\Blend\BlendMode;
use Domm98CZ\Image\Color\CallbackPixelFilter;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Color\PixelFilterInterface;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Image;

// The named operation shortcuts shared by ImageBuilder and AnimationBuilder; both implement apply().
trait FluentOperations
{
    abstract public function apply(OperationInterface $operation): static;

    public function resize(Dimensions $target, Interpolation $interpolation = Interpolation::Lanczos): static
    {
        return $this->apply(new Resize($target, $interpolation));
    }

    public function scale(float $factor, Interpolation $interpolation = Interpolation::Lanczos): static
    {
        return $this->apply(new Scale($factor, $interpolation));
    }

    public function scaleToWidth(int $width, Interpolation $interpolation = Interpolation::Lanczos): static
    {
        return $this->apply(new ScaleToWidth($width, $interpolation));
    }

    public function scaleToHeight(int $height, Interpolation $interpolation = Interpolation::Lanczos): static
    {
        return $this->apply(new ScaleToHeight($height, $interpolation));
    }

    public function fitInside(Dimensions $box, bool $upscale = false, Interpolation $interpolation = Interpolation::Lanczos): static
    {
        return $this->apply(new FitInside($box, $upscale, $interpolation));
    }

    public function fitOutside(Dimensions $box, bool $upscale = false, Interpolation $interpolation = Interpolation::Lanczos): static
    {
        return $this->apply(new FitOutside($box, $upscale, $interpolation));
    }

    public function thumbnail(Dimensions $size, Anchor $focus = Anchor::Center, bool $upscale = true, Interpolation $interpolation = Interpolation::Lanczos): static
    {
        return $this->apply(new Thumbnail($size, $focus, $upscale, $interpolation));
    }

    public function crop(Rectangle $area): static
    {
        return $this->apply(new Crop($area));
    }

    public function cropAnchored(Dimensions $size, Anchor $anchor = Anchor::Center): static
    {
        return $this->apply(new AnchoredCrop($size, $anchor));
    }

    public function rotate(Angle $angle, ?Color $background = null): static
    {
        return $this->apply($background === null ? new Rotate($angle) : new Rotate($angle, $background));
    }

    public function flip(FlipDirection $direction): static
    {
        return $this->apply(new Flip($direction));
    }

    public function autoOrient(): static
    {
        return $this->apply(new AutoOrient());
    }

    public function grayscale(): static
    {
        return $this->apply(new Grayscale());
    }

    public function invert(): static
    {
        return $this->apply(new Invert());
    }

    public function brightness(int $level): static
    {
        return $this->apply(new Brightness($level));
    }

    public function contrast(int $level): static
    {
        return $this->apply(new Contrast($level));
    }

    public function gamma(float $gamma): static
    {
        return $this->apply(new Gamma($gamma));
    }

    public function blur(float $sigma): static
    {
        return $this->apply(new Blur($sigma));
    }

    public function sharpen(float $amount = 1.0): static
    {
        return $this->apply(new Sharpen($amount));
    }

    public function colorize(Color $tint, float $strength = 0.5): static
    {
        return $this->apply(new Colorize($tint, $strength));
    }

    public function pad(int $left, int $top, int $right, int $bottom, ?Color $background = null): static
    {
        return $this->apply(new Pad($left, $top, $right, $bottom, $background ?? Color::transparent()));
    }

    public function extend(int $left, int $top, int $right, int $bottom, ?Color $background = null): static
    {
        return $this->pad($left, $top, $right, $bottom, $background);
    }

    public function border(int $size, ?Color $background = null): static
    {
        return $this->pad($size, $size, $size, $size, $background);
    }

    public function paste(Image $overlay, Point $position = new Point(0, 0), float $opacity = 1.0, BlendMode $mode = BlendMode::Normal): static
    {
        return $this->apply(new Composite($overlay, new Rectangle($position, $overlay->dimensions), $opacity, $mode));
    }

    public function insert(Image $overlay, Point $position = new Point(0, 0), float $opacity = 1.0, BlendMode $mode = BlendMode::Normal): static
    {
        return $this->paste($overlay, $position, $opacity, $mode);
    }

    public function trim(): static
    {
        return $this->apply(new Trim());
    }

    public function opacity(float $opacity): static
    {
        return $this->apply(new Opacity($opacity));
    }

    public function sepia(): static
    {
        return $this->apply(new Sepia());
    }

    public function hueSaturation(float $hue, float $saturation): static
    {
        return $this->apply(new HueSaturation($hue, $saturation));
    }

    public function pixelate(int $size): static
    {
        return $this->apply(new Pixelate($size));
    }

    public function roundedCorners(int $radius): static
    {
        return $this->apply(new RoundedCorners($radius));
    }

    public function mask(Image $mask): static
    {
        return $this->apply(new Mask($mask));
    }

    /** @param Canvas|Closure(Canvas): Canvas $drawing */
    public function draw(Canvas|Closure $drawing): static
    {
        $canvas = $drawing instanceof Closure ? $drawing(new Canvas()) : $drawing;

        return $canvas->shapes === [] ? $this : $this->apply(new Draw($canvas->shapes));
    }

    /** @param PixelFilterInterface|Closure(PixelBuffer): PixelBuffer $filter */
    public function applyPixels(PixelFilterInterface|Closure $filter, ?Rectangle $region = null): static
    {
        return $this->apply(new ApplyPixels($filter instanceof Closure ? new CallbackPixelFilter($filter) : $filter, $region));
    }
}
