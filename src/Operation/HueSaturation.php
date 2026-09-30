<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Operation;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;

final readonly class HueSaturation implements PixelOperationInterface
{
    public function __construct(
        public float $hue,
        public float $saturation,
    ) {
        ColorAdjustment::assertRange('HueSaturation', 'hue', $hue, -180, 180);
        ColorAdjustment::assertRange('HueSaturation', 'saturation', $saturation, -100, 100);
    }

    public function area(Dimensions $image): Rectangle
    {
        return Rectangle::covering($image);
    }

    public function apply(PixelBuffer $pixels): PixelBuffer
    {
        $bytes = $pixels->bytes;
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += PixelBuffer::BYTES_PER_PIXEL) {
            [$red, $green, $blue] = self::adjust(ord($bytes[$offset]), ord($bytes[$offset + 1]), ord($bytes[$offset + 2]));
            $bytes[$offset] = chr($red);
            $bytes[$offset + 1] = chr($green);
            $bytes[$offset + 2] = chr($blue);
        }

        return $pixels->withBytes($bytes);
    }

    /** @return array{int, int, int} */
    private function adjust(int $red, int $green, int $blue): array
    {
        $red /= 255;
        $green /= 255;
        $blue /= 255;
        $maximum = max($red, $green, $blue);
        $minimum = min($red, $green, $blue);
        $delta = $maximum - $minimum;
        $lightness = ($maximum + $minimum) / 2;
        $saturation = $delta === 0.0 ? 0.0 : $delta / (1 - abs(2 * $lightness - 1));
        $hue = $delta === 0.0 ? 0.0 : match ($maximum) {
            $red => fmod(($green - $blue) / $delta + 6, 6),
            $green => ($blue - $red) / $delta + 2,
            default => ($red - $green) / $delta + 4,
        } / 6;
        $hue = fmod($hue + $this->hue / 360 + 1, 1);
        $saturation = max(0.0, min(1.0, $saturation * (1 + $this->saturation / 100)));
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $secondary = $chroma * (1 - abs(fmod($hue * 6, 2) - 1));
        $match = $lightness - $chroma / 2;
        [$red, $green, $blue] = match ((int) floor($hue * 6)) {
            0 => [$chroma, $secondary, 0.0],
            1 => [$secondary, $chroma, 0.0],
            2 => [0.0, $chroma, $secondary],
            3 => [0.0, $secondary, $chroma],
            4 => [$secondary, 0.0, $chroma],
            default => [$chroma, 0.0, $secondary],
        };

        return [(int) round(($red + $match) * 255), (int) round(($green + $match) * 255), (int) round(($blue + $match) * 255)];
    }
}
