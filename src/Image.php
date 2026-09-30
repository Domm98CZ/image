<?php

declare(strict_types=1);

namespace Domm98CZ\Image;

use Domm98CZ\Image\Analysis\BlurHash;
use Domm98CZ\Image\Analysis\ColorQuantizer;
use Domm98CZ\Image\Analysis\Histogram;
use Domm98CZ\Image\Analysis\PerceptualHash;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\ImageHandleInterface;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Metadata\Orientation;

// Materialized and immutable: nothing may mutate the handle once an Image wraps it (the executor copies first).
final readonly class Image
{
    public Dimensions $dimensions;

    /** @internal Created by Pipeline\Executor. */
    public function __construct(
        private ImageHandleInterface $handle,
        private DriverInterface $driver,
        public Orientation $orientation,
        public ?FormatName $sourceFormat,
    ) {
        $this->dimensions = $driver->dimensions($handle);
    }

    public function width(): int
    {
        return $this->dimensions->width;
    }

    public function height(): int
    {
        return $this->dimensions->height;
    }

    public function colorAt(Point $point): Color
    {
        return $this->driver->colorAt($this->handle, $point);
    }

    public function pixels(?Rectangle $area = null): PixelBuffer
    {
        return $this->driver->readPixels($this->handle, $area ?? Rectangle::covering($this->dimensions));
    }

    public function hasAlpha(): bool
    {
        $bytes = $this->pixels()->bytes;
        for ($offset = 3, $length = strlen($bytes); $offset < $length; $offset += PixelBuffer::BYTES_PER_PIXEL) {
            if ($bytes[$offset] !== "\xff") {
                return true;
            }
        }

        return false;
    }

    public function isOpaque(): bool
    {
        return !$this->hasAlpha();
    }

    public function averageColor(): Color
    {
        $bytes = $this->pixels()->bytes;
        $totals = [0, 0, 0, 0];
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += PixelBuffer::BYTES_PER_PIXEL) {
            $totals[0] += ord($bytes[$offset]);
            $totals[1] += ord($bytes[$offset + 1]);
            $totals[2] += ord($bytes[$offset + 2]);
            $totals[3] += ord($bytes[$offset + 3]);
        }
        $count = $this->dimensions->pixelCount();

        return new Color(
            (int) round($totals[0] / $count),
            (int) round($totals[1] / $count),
            (int) round($totals[2] / $count),
            (int) round($totals[3] / $count),
        );
    }

    /** @return list<Color> ordered by cluster population, most populous first */
    public function palette(int $count = 5): array
    {
        return ColorQuantizer::palette($this->pixels(), $count);
    }

    public function dominantColor(): Color
    {
        return $this->palette()[0];
    }

    public function histogram(): Histogram
    {
        return Histogram::of($this->pixels());
    }

    public function blurhash(int $componentsX = 4, int $componentsY = 3): string
    {
        return BlurHash::encode($this->pixels(), $componentsX, $componentsY);
    }

    public function perceptualHash(): string
    {
        return PerceptualHash::of($this->pixels());
    }

    public function encode(OutputFormatInterface $output): EncodedImage
    {
        return $this->driver->encode($this->handle, $output);
    }

    public function driverName(): DriverName
    {
        return $this->driver->name();
    }

    /** @internal */
    public function handle(): ImageHandleInterface
    {
        return $this->handle;
    }

    /** @internal */
    public function driver(): DriverInterface
    {
        return $this->driver;
    }
}
