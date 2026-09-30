<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Color\PixelFilterInterface;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;

final readonly class ApplyPixels implements PixelOperationInterface
{
    public function __construct(
        public PixelFilterInterface $filter,
        // Null means the whole image; a region keeps buffer I/O proportional to what the filter touches.
        public ?Rectangle $region = null,
    ) {}

    public function area(Dimensions $image): Rectangle
    {
        $area = $this->region ?? Rectangle::covering($image);
        $area->assertWithin($image);

        return $area;
    }

    public function apply(PixelBuffer $pixels): PixelBuffer
    {
        $result = $this->filter->apply($pixels);
        if (!$result->dimensions->equals($pixels->dimensions)) {
            throw InvalidOperationException::filterChangedDimensions($this->filter::class);
        }

        return $result;
    }
}
