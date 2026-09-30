<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Pipeline;

use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\InvalidConfigurationException;

// Relative costs in nanoseconds per pixel, fitted to 12 MP (4000x3000) measurements on PHP 8.4 with system libgd
// and ImageMagick 7: a native step ~60 ms, a PHP pass including the GD buffer round-trip ~1.8 s,
// a lossless PNG hand-off between drivers 1.5-2.4 s each way on photographic content.
final readonly class CostModel
{
    public function __construct(
        public float $nativeNanosPerPixel = 5.0,
        public float $phpFallbackNanosPerPixel = 150.0,
        // Far above a transfer, so a lossless path on another driver always wins when one exists.
        public float $degradedNanosPerPixel = 10_000.0,
        // PNG filtering and deflate scale with image entropy: a smooth synthetic image hands off at ~45 ns/px, a photo
        // with sensor noise at 130-200, so the transfer is priced for photos, the usual input, and costs about as much
        // as a PHP pass: moving an image to save a single fallback step no longer pays.
        public float $transferNanosPerPixel = 150.0,
    ) {
        self::assertNonNegative('degradedNanosPerPixel', $degradedNanosPerPixel);

        $others = [
            'nativeNanosPerPixel' => $nativeNanosPerPixel,
            'phpFallbackNanosPerPixel' => $phpFallbackNanosPerPixel,
            'transferNanosPerPixel' => $transferNanosPerPixel,
        ];
        foreach ($others as $cost => $value) {
            self::assertNonNegative($cost, $value);
            // A degraded step priced at or below any other cost would let the planner trade output quality for speed.
            if ($degradedNanosPerPixel <= $value) {
                throw InvalidConfigurationException::degradedCostNotHighest($cost, $value, $degradedNanosPerPixel);
            }
        }
    }

    public function step(Support $support, int $pixels): float
    {
        return match ($support) {
            Support::Native => $this->nativeNanosPerPixel * $pixels,
            Support::PhpFallback => $this->phpFallbackNanosPerPixel * $pixels,
            Support::Degraded => $this->degradedNanosPerPixel * $pixels,
            Support::None => INF,
        };
    }

    public function transfer(int $pixels): float
    {
        return $this->transferNanosPerPixel * $pixels;
    }

    private static function assertNonNegative(string $cost, float $value): void
    {
        if ($value < 0.0 || is_nan($value)) {
            throw InvalidConfigurationException::costNotNonNegative($cost, $value);
        }
    }
}
