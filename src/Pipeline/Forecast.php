<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Pipeline;

use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Operation\AutoOrient;
use Domm98CZ\Image\Operation\CompositeOperationInterface;
use Domm98CZ\Image\Operation\ExpansionContext;
use Domm98CZ\Image\Operation\OperationInterface;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;

// Predicted pixel counts per step; only used to weigh costs, never to validate (the executor does that).
final readonly class Forecast
{
    private const MAX_EXPANSION_DEPTH = 16;

    /** @param list<int> $stepPixels */
    private function __construct(
        public int $initialPixels,
        public array $stepPixels,
        public int $finalPixels,
    ) {}

    /** @param list<OperationInterface> $operations */
    public static function of(Dimensions $initial, Orientation $orientation, array $operations): self
    {
        $dimensions = $initial;
        $stepPixels = [];
        foreach ($operations as $operation) {
            $after = self::fold($operation, $dimensions, $orientation, 0);
            $stepPixels[] = max($dimensions->pixelCount(), $after->pixelCount());
            $dimensions = $after;
            if ($operation instanceof AutoOrient) {
                $orientation = Orientation::TopLeft;
            }
        }

        return new self($initial->pixelCount(), $stepPixels, $dimensions->pixelCount());
    }

    private static function fold(OperationInterface $operation, Dimensions $dimensions, Orientation $orientation, int $depth): Dimensions
    {
        try {
            if ($operation instanceof PrimitiveOperationInterface) {
                return $operation->resultingDimensions($dimensions);
            }
            if ($operation instanceof CompositeOperationInterface && $depth < self::MAX_EXPANSION_DEPTH) {
                foreach ($operation->expand(new ExpansionContext($dimensions, $orientation)) as $expanded) {
                    $dimensions = self::fold($expanded, $dimensions, $orientation, $depth + 1);
                }
            }
        } catch (InvalidOperationException) {
            // Predictions can be a pixel off (arbitrary rotations); the executor re-validates on real dimensions.
        }

        return $dimensions;
    }
}
