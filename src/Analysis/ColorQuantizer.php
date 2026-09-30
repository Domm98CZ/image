<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Analysis;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Operation\ColorAdjustment;

// Median-cut: recursively split the widest-range bucket until there are enough, or no bucket can split further.
final class ColorQuantizer
{
    // Sampling every pixel of a large photo is wasted work; a grid of this size already stabilizes the result.
    private const MAX_SAMPLES = 4_096;

    /** @return list<Color> ordered by cluster population, most populous first */
    public static function palette(PixelBuffer $pixels, int $count): array
    {
        ColorAdjustment::assertRange('palette', 'count', $count, 1, PHP_INT_MAX);
        $samples = self::sample($pixels);
        $buckets = [$samples];
        while (count($buckets) < $count) {
            $splitIndex = null;
            $splitChannel = 0;
            $widestRange = 0;
            foreach ($buckets as $index => $bucket) {
                if (count($bucket) < 2) {
                    continue;
                }
                foreach ([0, 1, 2] as $channel) {
                    $values = array_column($bucket, $channel);
                    $range = max($values) - min($values);
                    if ($range > $widestRange) {
                        $widestRange = $range;
                        $splitIndex = $index;
                        $splitChannel = $channel;
                    }
                }
            }
            if ($splitIndex === null) {
                break;
            }
            $bucket = $buckets[$splitIndex];
            usort($bucket, static fn(array $a, array $b): int => $a[$splitChannel] <=> $b[$splitChannel]);
            $middle = intdiv(count($bucket), 2);
            array_splice($buckets, $splitIndex, 1, [array_slice($bucket, 0, $middle), array_slice($bucket, $middle)]);
        }
        usort($buckets, static fn(array $a, array $b): int => count($b) <=> count($a));

        return array_map(self::average(...), $buckets);
    }

    /** @return list<array{0:int,1:int,2:int}> */
    private static function sample(PixelBuffer $pixels): array
    {
        $bytes = $pixels->bytes;
        $pixelCount = $pixels->dimensions->pixelCount();
        $stride = max(1, intdiv($pixelCount, self::MAX_SAMPLES));
        $samples = [];
        for ($pixel = 0; $pixel < $pixelCount; $pixel += $stride) {
            $offset = $pixel * PixelBuffer::BYTES_PER_PIXEL;
            $samples[] = [ord($bytes[$offset]), ord($bytes[$offset + 1]), ord($bytes[$offset + 2])];
        }

        return $samples;
    }

    /** @param list<array{0:int,1:int,2:int}> $bucket */
    private static function average(array $bucket): Color
    {
        $totals = [0, 0, 0];
        foreach ($bucket as $pixel) {
            $totals[0] += $pixel[0];
            $totals[1] += $pixel[1];
            $totals[2] += $pixel[2];
        }
        $count = count($bucket);

        return new Color(
            (int) round($totals[0] / $count),
            (int) round($totals[1] / $count),
            (int) round($totals[2] / $count),
        );
    }
}
