<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Security;

use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Header\HeaderProbeInterface;
use Domm98CZ\Image\Format\Header\ImageHeader;

final readonly class InputGuard
{
    private const BYTES_PER_PIXEL = 4;

    private LimitChecker $checker;

    public function __construct(
        private Limits $limits,
        private HeaderProbeInterface $probe = new HeaderProbe(),
    ) {
        $this->checker = new LimitChecker($limits);
    }

    public function inspect(string $bytes, MemoryBudget $memory): ValidatedInput
    {
        $this->checker->check(LimitType::InputBytes, strlen($bytes), $this->limits->maxInputBytes);

        $header = $this->probe->probe(new BinaryString($bytes));
        $this->checker->checkDimensions($header->canvas);
        $this->checker->checkDimensions($header->largestFrame);
        $this->checker->checkAnimation($header->frameCount, $header->canvas);

        if ($this->limits->checkMemoryLimit) {
            $estimate = self::estimateDecodeMemory($header);
            if (!$memory->allows($estimate)) {
                throw new LimitExceededException(new LimitViolation(LimitType::Memory, $estimate, $memory->availableBytes ?? PHP_INT_MAX));
            }
        }

        return new ValidatedInput($bytes, $header);
    }

    // Bytes the library encoded itself for a driver hand-off: the pixel limits still hold, the byte cap and the memory
    // estimate are for untrusted input (the pixels are already decoded and the PNG can be far larger than a photo).
    public function inspectHandoff(string $bytes): ValidatedInput
    {
        $header = $this->probe->probe(new BinaryString($bytes));
        $this->checker->checkDimensions($header->canvas);

        return new ValidatedInput($bytes, $header);
    }

    // Every decoded frame plus one working copy for the pipeline run.
    public static function estimateDecodeMemory(ImageHeader $header): int
    {
        return ($header->frameCount + 1) * $header->canvas->pixelCount() * self::BYTES_PER_PIXEL;
    }
}
