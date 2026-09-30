<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Blend;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidOperationException;

// Pure-PHP source-over compositing with a blend mode; used where a driver has no native equivalent.
final readonly class Compositor
{
    public function blend(PixelBuffer $backdrop, PixelBuffer $source, BlendMode $mode, float $opacity = 1.0): PixelBuffer
    {
        if (!$backdrop->dimensions->equals($source->dimensions)) {
            throw InvalidOperationException::outOfRange('Compositor', 'source size', $source->dimensions->pixelCount(), (string) $backdrop->dimensions->pixelCount());
        }
        $result = $backdrop->bytes;
        $length = strlen($result);
        // Blend results depend only on (backdrop, source) channel pairs, so cache them per pair.
        $mixed = [];
        for ($offset = 0; $offset < $length; $offset += 4) {
            $sourceAlpha = ord($source->bytes[$offset + 3]) / 255 * $opacity;
            if ($sourceAlpha <= 0.0) {
                continue;
            }
            $backdropAlpha = ord($result[$offset + 3]) / 255;
            $outAlpha = $sourceAlpha + $backdropAlpha * (1 - $sourceAlpha);
            for ($channel = 0; $channel < 3; ++$channel) {
                $cb = ord($result[$offset + $channel]);
                $cs = ord($source->bytes[$offset + $channel]);
                $blended = $mixed[$cb << 8 | $cs] ??= $mode->mix($cb / 255, $cs / 255);
                $sourceColor = (1 - $backdropAlpha) * ($cs / 255) + $backdropAlpha * $blended;
                $out = ($sourceAlpha * $sourceColor + $backdropAlpha * ($cb / 255) * (1 - $sourceAlpha)) / $outAlpha;
                $result[$offset + $channel] = chr((int) round(max(0.0, min(1.0, $out)) * 255));
            }
            $result[$offset + 3] = chr((int) round($outAlpha * 255));
        }

        return $backdrop->withBytes($result);
    }
}
