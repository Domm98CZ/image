<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Pipeline;

use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\UnsupportedFormatException;
use Domm98CZ\Image\Exception\UnsupportedOperationException;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Operation\CompositeOperationInterface;
use Domm98CZ\Image\Operation\OperationInterface;
use Domm98CZ\Image\Operation\PixelOperationInterface;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Domm98CZ\Image\Pipeline\Source\BlankSource;
use Domm98CZ\Image\Pipeline\Source\DecodableSource;
use Domm98CZ\Image\Pipeline\Source\ImageSource;
use Domm98CZ\Image\Pipeline\Source\SourceInterface;

// Cheapest path through (step x driver): per-step support costs plus a transfer cost for every driver switch.
final readonly class Planner
{
    public function __construct(
        private DriverRegistry $drivers,
        private CostModel $costs = new CostModel(),
    ) {}

    /** @param list<OperationInterface> $operations */
    public function plan(SourceInterface $source, array $operations, ?OutputFormatInterface $output = null): Plan
    {
        $drivers = $this->drivers->drivers;
        $forecast = Forecast::of(self::sourceDimensions($source), self::sourceOrientation($source), $operations);
        $sourceDrivers = $this->sourceDrivers($source);

        // $cost[$d]: cheapest way to have the image on $drivers[$d] after the current step; $paths mirror it.
        $cost = [];
        $paths = [];
        foreach ($drivers as $index => $driver) {
            [$cost[$index], $origin] = $this->cheapestArrival($source, $sourceDrivers, $driver, $forecast->initialPixels);
            $paths[$index] = ['source' => $origin, 'steps' => []];
        }

        foreach ($operations as $step => $operation) {
            $pixels = $forecast->stepPixels[$step];
            $nextCost = [];
            $nextPaths = [];
            foreach ($drivers as $index => $driver) {
                [$bestCost, $from] = $this->cheapestSwitch($cost, $drivers, $index, $pixels);
                $nextCost[$index] = $bestCost + $this->costs->step(self::operationSupport($driver, $operation), $pixels);
                $nextPaths[$index] = $paths[$from];
                $nextPaths[$index]['steps'][] = new PlannedStep($operation, $driver);
            }
            $cost = $nextCost;
            $paths = $nextPaths;
        }

        $best = null;
        $bestCost = INF;
        foreach ($drivers as $index => $driver) {
            if ($output === null) {
                $total = $cost[$index];
                $from = $index;
            } else {
                [$arrival, $from] = $this->cheapestSwitch($cost, $drivers, $index, $forecast->finalPixels);
                $total = $arrival + $this->costs->step($driver->encodingSupport($output), $forecast->finalPixels);
            }
            if ($total < $bestCost) {
                $bestCost = $total;
                $best = [$index, $from];
            }
        }

        if ($best === null) {
            throw $this->explainFailure($source, $operations, $output);
        }
        [$index, $from] = $best;
        $path = $paths[$from];
        $sourceDriver = $path['source'] ?? $drivers[$from];

        return new Plan($sourceDriver, $path['steps'], $output === null ? null : $drivers[$index]);
    }

    /**
     * @param list<OperationInterface> $operations
     * @return list<class-string<PrimitiveOperationInterface>>
     */
    public static function requiredPrimitives(array $operations): array
    {
        $required = [];
        foreach ($operations as $operation) {
            if ($operation instanceof PrimitiveOperationInterface) {
                $required[] = $operation::class;
            } elseif ($operation instanceof CompositeOperationInterface) {
                array_push($required, ...$operation->requiredPrimitives());
            }
        }

        return array_values(array_unique($required));
    }

    // A composite runs entirely on one driver, so it costs as much as its worst-supported primitive.
    private static function operationSupport(DriverInterface $driver, OperationInterface $operation): Support
    {
        if ($operation instanceof PixelOperationInterface) {
            return $driver->pixelAccess();
        }
        $worst = Support::Native;
        foreach (self::requiredPrimitives([$operation]) as $primitive) {
            $worst = $worst->worse($driver->support($primitive));
        }

        return $worst;
    }

    // A decode that would ignore an embedded wide-gamut or CMYK profile is only as good as its color management.
    private static function decodeSupport(DriverInterface $driver, DecodableSource $source): Support
    {
        $support = $driver->decodingSupport($source->input->header->format);

        return $source->needsColorManagement() ? $support->worse($driver->colorManagement()) : $support;
    }

    /**
     * @param list<DriverInterface> $sourceDrivers
     * @return array{float, ?DriverInterface}
     */
    private function cheapestArrival(SourceInterface $source, array $sourceDrivers, DriverInterface $driver, int $pixels): array
    {
        $best = [INF, null];
        foreach ($sourceDrivers as $sourceDriver) {
            $cost = ($sourceDriver === $driver ? 0.0 : $this->costs->transfer($pixels))
                + ($source instanceof DecodableSource ? $this->costs->step(self::decodeSupport($sourceDriver, $source), $pixels) : 0.0);
            if ($cost < $best[0]) {
                $best = [$cost, $sourceDriver];
            }
        }

        return $best;
    }

    /**
     * @param array<int, float> $cost
     * @param list<DriverInterface> $drivers
     * @return array{float, int}
     */
    private function cheapestSwitch(array $cost, array $drivers, int $target, int $pixels): array
    {
        // Ties keep the path already on the target driver (so equal-cost moves happen early), then registry order.
        $best = [$cost[$target], $target];
        foreach ($drivers as $index => $driver) {
            $candidate = $cost[$index] + $this->costs->transfer($pixels);
            if ($index !== $target && $candidate < $best[0]) {
                $best = [$candidate, $index];
            }
        }

        return $best;
    }

    /** @return list<DriverInterface> */
    private function sourceDrivers(SourceInterface $source): array
    {
        if ($source instanceof ImageSource) {
            // Handles bind to a driver type, not an instance: an Image from another factory stays on the same-named
            // registry driver. A name the registry lacks keeps the foreign instance and is priced as a transfer.
            $driver = $source->image->driver();

            return [$this->drivers->get($driver->name()) ?? $driver];
        }
        if ($source instanceof DecodableSource) {
            $format = $source->input->header->format;

            return array_values(array_filter($this->drivers->drivers, static fn(DriverInterface $driver): bool => $driver->decodingSupport($format)->isSupported()));
        }

        return $this->drivers->drivers;
    }

    private static function sourceDimensions(SourceInterface $source): Dimensions
    {
        return match (true) {
            $source instanceof DecodableSource => $source->input->header->canvas,
            $source instanceof ImageSource => $source->image->dimensions,
            $source instanceof BlankSource => $source->dimensions,
            default => new Dimensions(1, 1),
        };
    }

    private static function sourceOrientation(SourceInterface $source): Orientation
    {
        return match (true) {
            $source instanceof DecodableSource => $source->orientation,
            $source instanceof ImageSource => $source->image->orientation,
            default => Orientation::TopLeft,
        };
    }

    /** @param list<OperationInterface> $operations */
    private function explainFailure(SourceInterface $source, array $operations, ?OutputFormatInterface $output): UnsupportedOperationException|UnsupportedFormatException
    {
        $drivers = $this->drivers->drivers;
        $any = static fn(callable $test): bool => array_filter($drivers, $test) !== [];

        if ($source instanceof DecodableSource && $this->sourceDrivers($source) === []) {
            return UnsupportedFormatException::noDriverCanDecode($source->input->header->format);
        }
        foreach (self::requiredPrimitives($operations) as $primitive) {
            if (!$any(static fn(DriverInterface $driver): bool => $driver->support($primitive)->isSupported())) {
                return UnsupportedOperationException::noDriverSupports($primitive, $this->drivers->names());
            }
        }
        if ($output !== null && !$any(static fn(DriverInterface $driver): bool => $driver->encodingSupport($output)->isSupported())) {
            return UnsupportedFormatException::noDriverCanEncode($output->format());
        }

        return UnsupportedOperationException::noExecutionPlan($this->drivers->names());
    }
}
