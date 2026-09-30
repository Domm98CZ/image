<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark;

use Domm98CZ\Image\Blend\BlendMode;
use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Position;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\Operation\ColorAdjustment;
use Domm98CZ\Image\Operation\Composite;
use Domm98CZ\Image\Operation\CompositeOperationInterface;
use Domm98CZ\Image\Operation\ExpansionContext;

final readonly class VisibleWatermark implements PixelWatermarkInterface, CompositeOperationInterface
{
    public function __construct(
        public Image $overlay,
        public Position $position = new Position(Anchor::BottomRight),
        public float $opacity = 1.0,
        public BlendMode $mode = BlendMode::Normal,
        // Overlay width as a fraction of the image width (aspect kept); null keeps the overlay's own size.
        public ?float $relativeWidth = null,
    ) {
        ColorAdjustment::assertRange('VisibleWatermark', 'opacity', $opacity, 0, 1);
        if ($relativeWidth !== null) {
            ColorAdjustment::assertRange('VisibleWatermark', 'relativeWidth', $relativeWidth, 0.001, 1);
        }
    }

    public function expand(ExpansionContext $context): array
    {
        $size = $this->relativeWidth === null
            ? $this->overlay->dimensions
            : $this->overlay->dimensions->scaledToWidth(max(1, (int) round($context->dimensions->width * $this->relativeWidth)));

        return [new Composite(
            $this->overlay,
            new Rectangle($this->position->resolve($context->dimensions, $size), $size),
            $this->opacity,
            $this->mode,
        )];
    }

    public function requiredPrimitives(): array
    {
        return [Composite::class];
    }

    public function withOpacity(float $opacity): self
    {
        return new self($this->overlay, $this->position, $opacity, $this->mode, $this->relativeWidth);
    }
}
