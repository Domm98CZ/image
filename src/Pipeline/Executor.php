<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Pipeline;

use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\ImageHandleInterface;
use Domm98CZ\Image\Exception\InvalidOperationException;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Operation\AutoOrient;
use Domm98CZ\Image\Operation\CompositeOperationInterface;
use Domm98CZ\Image\Operation\ExpansionContext;
use Domm98CZ\Image\Operation\OperationInterface;
use Domm98CZ\Image\Operation\PixelOperationInterface;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Pipeline\Source\BlankSource;
use Domm98CZ\Image\Pipeline\Source\DecodableSource;
use Domm98CZ\Image\Pipeline\Source\ImageSource;
use Domm98CZ\Image\Pipeline\Source\SourceInterface;
use Domm98CZ\Image\Security\LimitChecker;

final readonly class Executor
{
    private const MAX_EXPANSION_DEPTH = 16;

    public function __construct(
        private LimitChecker $limits,
        private Transfer $transfer,
    ) {}

    public function run(Plan $plan, SourceInterface $source): Image
    {
        $current = $plan->sourceDriver;
        if ($source instanceof ImageSource && $plan->steps === [] && $plan->finalDriver() === $current) {
            return $source->image;
        }

        // Only the first mutating step needs a private copy; a transfer already produces a fresh handle.
        $firstUser = $plan->steps[0]->driver ?? $plan->finalDriver();
        $state = $this->materialize($current, $source, ownCopy: $firstUser === $current);

        foreach ($plan->steps as $step) {
            if ($step->driver !== $current) {
                $state = $state->withHandle($this->transfer->move($state->handle, $current, $step->driver));
                $current = $step->driver;
            }
            $state = $this->applyAll($current, $state, [$step->operation], 0);
        }
        if ($plan->finalDriver() !== $current) {
            $state = $state->withHandle($this->transfer->move($state->handle, $current, $plan->finalDriver()));
            $current = $plan->finalDriver();
        }

        return new Image($state->handle, $current, $state->orientation, $state->sourceFormat);
    }

    private function materialize(DriverInterface $driver, SourceInterface $source, bool $ownCopy): ExecutionState
    {
        return match (true) {
            $source instanceof DecodableSource => new ExecutionState(
                $driver->decode($source->input),
                $source->orientation,
                $source->input->header->format,
            ),
            $source instanceof ImageSource => new ExecutionState(
                $ownCopy ? $driver->copy($source->image->handle()) : $source->image->handle(),
                $source->image->orientation,
                $source->image->sourceFormat,
            ),
            $source instanceof BlankSource => new ExecutionState(
                $driver->create($source->dimensions, $source->background),
                Orientation::TopLeft,
                null,
            ),
            default => throw InvalidOperationException::unknownSource($source::class),
        };
    }

    /** @param list<OperationInterface> $operations */
    private function applyAll(DriverInterface $driver, ExecutionState $state, array $operations, int $depth): ExecutionState
    {
        foreach ($operations as $operation) {
            if ($operation instanceof PixelOperationInterface) {
                $state = $state->withHandle($this->applyPixels($driver, $state->handle, $operation));
                continue;
            }
            if ($operation instanceof PrimitiveOperationInterface) {
                $state = $state->withHandle($this->applyPrimitive($driver, $state->handle, $operation));
                continue;
            }
            if (!$operation instanceof CompositeOperationInterface) {
                throw InvalidOperationException::neitherPrimitiveNorComposite($operation::class);
            }
            if ($depth >= self::MAX_EXPANSION_DEPTH) {
                throw InvalidOperationException::expansionTooDeep($operation::class, self::MAX_EXPANSION_DEPTH);
            }
            $context = new ExpansionContext($driver->dimensions($state->handle), $state->orientation);
            $state = $this->applyAll($driver, $state, $operation->expand($context), $depth + 1);
            if ($operation instanceof AutoOrient) {
                $state = $state->withOrientation(Orientation::TopLeft);
            }
        }

        return $state;
    }

    private function applyPixels(DriverInterface $driver, ImageHandleInterface $handle, PixelOperationInterface $operation): ImageHandleInterface
    {
        $area = $operation->area($driver->dimensions($handle));

        return $driver->writePixels($handle, $operation->apply($driver->readPixels($handle, $area)), $area->origin);
    }

    private function applyPrimitive(DriverInterface $driver, ImageHandleInterface $handle, PrimitiveOperationInterface $operation): ImageHandleInterface
    {
        // Validates the step (e.g. crop bounds) and stops oversized results before the driver allocates them.
        $this->limits->checkDimensions($operation->resultingDimensions($driver->dimensions($handle)));

        return $driver->apply($handle, $operation);
    }
}
