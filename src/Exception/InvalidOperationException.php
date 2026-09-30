<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;

final class InvalidOperationException extends InvalidArgumentException
{
    public static function cropOutsideImage(Rectangle $area, Dimensions $image): self
    {
        return new self(sprintf(
            'Crop area %dx%d at %d,%d is not fully inside the %dx%d image.',
            $area->dimensions->width,
            $area->dimensions->height,
            $area->origin->x,
            $area->origin->y,
            $image->width,
            $image->height,
        ));
    }

    public static function outOfRange(string $operation, string $parameter, int|float $value, string $range): self
    {
        return new self(sprintf('%s: %s must be within %s, got %s.', $operation, $parameter, $range, self::number($value)));
    }

    public static function filterChangedDimensions(string $filter): self
    {
        return new self(sprintf('Pixel filter %s must return a buffer with the same dimensions it received.', $filter));
    }

    public static function maskDimensionsMismatch(Dimensions $image, Dimensions $mask): self
    {
        return new self(sprintf('Mask dimensions %dx%d must match image dimensions %dx%d.', $mask->width, $mask->height, $image->width, $image->height));
    }

    public static function noCandidates(): self
    {
        return new self('encodeSmallest() needs at least one output format.');
    }

    public static function neitherPrimitiveNorComposite(string $operation): self
    {
        return new self(sprintf('Operation %s must implement PrimitiveOperationInterface or CompositeOperationInterface.', $operation));
    }

    public static function unknownSource(string $source): self
    {
        return new self(sprintf('Unknown pipeline source %s.', $source));
    }

    public static function expansionTooDeep(string $operation, int $maxDepth): self
    {
        return new self(sprintf('Operation %s expanded more than %d levels deep; a composite operation likely expands into itself.', $operation, $maxDepth));
    }

    public static function areaOutsideImage(Rectangle $area, Dimensions $image): self
    {
        return new self(sprintf(
            'Pixel area %dx%d at %d,%d is not fully inside the %dx%d image.',
            $area->dimensions->width,
            $area->dimensions->height,
            $area->origin->x,
            $area->origin->y,
            $image->width,
            $image->height,
        ));
    }

    public static function pointOutsideImage(Point $point, Dimensions $image): self
    {
        return new self(sprintf('Point %d,%d is outside the %dx%d image.', $point->x, $point->y, $image->width, $image->height));
    }

    public static function mismatchedHashLength(int $first, int $second): self
    {
        return new self(sprintf('Cannot compare hashes of different length: %d and %d.', $first, $second));
    }
}
