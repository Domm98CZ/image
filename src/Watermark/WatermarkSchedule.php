<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark;

use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Format\Output\WebpOutput;
use Domm98CZ\Image\Operation\OperationInterface;
use Domm98CZ\Image\Watermark\Steganography\SteganographicWatermarkInterface;
use ReflectionClass;

// Steganographic marks go last (anything after them would damage them) and must not meet a lossy encoder they cannot survive.
final class WatermarkSchedule
{
    /**
     * @param list<OperationInterface> $operations
     * @return list<OperationInterface>
     */
    public static function order(array $operations): array
    {
        $regular = array_filter($operations, static fn(OperationInterface $operation): bool => !$operation instanceof SteganographicWatermarkInterface);
        $hidden = array_filter($operations, static fn(OperationInterface $operation): bool => $operation instanceof SteganographicWatermarkInterface);

        return [...array_values($regular), ...array_values($hidden)];
    }

    /** @param list<OperationInterface> $operations */
    public static function assertSurvives(array $operations, OutputFormatInterface $output): void
    {
        foreach ($operations as $operation) {
            if ($operation instanceof SteganographicWatermarkInterface && !$operation->survivesLossyEncoding() && !self::isLossless($output)) {
                throw InvalidWatermarkException::lostByOutput((new ReflectionClass($operation))->getShortName(), $output->format());
            }
        }
    }

    public static function isLossless(OutputFormatInterface $output): bool
    {
        return $output instanceof PngOutput || ($output instanceof WebpOutput && $output->lossless);
    }
}
