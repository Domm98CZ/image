<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;

final readonly class Pixelate implements PixelOperationInterface
{
    public function __construct(
        public int $size,
    ) {
        ColorAdjustment::assertRange('Pixelate', 'size', $size, 1, Dimensions::MAX_SIDE);
    }

    public function area(Dimensions $image): Rectangle
    {
        return Rectangle::covering($image);
    }

    public function apply(PixelBuffer $pixels): PixelBuffer
    {
        $dimensions = $pixels->dimensions;
        $source = $pixels->bytes;
        $result = $source;
        for ($top = 0; $top < $dimensions->height; $top += $this->size) {
            for ($left = 0; $left < $dimensions->width; $left += $this->size) {
                $width = min($this->size, $dimensions->width - $left);
                $height = min($this->size, $dimensions->height - $top);
                $totals = [0, 0, 0, 0];
                for ($y = $top; $y < $top + $height; ++$y) {
                    for ($x = $left; $x < $left + $width; ++$x) {
                        $offset = ($y * $dimensions->width + $x) * PixelBuffer::BYTES_PER_PIXEL;
                        foreach (range(0, 3) as $channel) {
                            $totals[$channel] += ord($source[$offset + $channel]);
                        }
                    }
                }
                $color = array_map(static fn(int $total): string => chr((int) round($total / ($width * $height))), $totals);
                for ($y = $top; $y < $top + $height; ++$y) {
                    for ($x = $left; $x < $left + $width; ++$x) {
                        $offset = ($y * $dimensions->width + $x) * PixelBuffer::BYTES_PER_PIXEL;
                        foreach (range(0, 3) as $channel) {
                            $result[$offset + $channel] = $color[$channel];
                        }
                    }
                }
            }
        }

        return $pixels->withBytes($result);
    }
}
