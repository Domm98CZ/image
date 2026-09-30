<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Geometry\Anchor;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;

final readonly class AnchoredCrop implements CompositeOperationInterface
{
    public function __construct(
        public Dimensions $size,
        public Anchor $anchor = Anchor::Center,
    ) {}

    public function expand(ExpansionContext $context): array
    {
        $current = $context->dimensions;
        // Never crops beyond the image: a side larger than the image keeps the image's side.
        $size = new Dimensions(min($this->size->width, $current->width), min($this->size->height, $current->height));
        if ($size->equals($current)) {
            return [];
        }

        return [new Crop(new Rectangle($this->anchor->placeWithin($current, $size), $size))];
    }

    public function requiredPrimitives(): array
    {
        return [Crop::class];
    }
}
