<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark\Steganography;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Operation\ColorAdjustment;
use Domm98CZ\Image\Watermark\Payload;
use Domm98CZ\Image\Watermark\SecretKey;

// One bit per 8x8 luma block (the JPEG grid), repeated across blocks: small payload, survives JPEG re-compression.
final readonly class DctWatermark implements SteganographicWatermarkInterface
{
    public const MAX_PAYLOAD_BYTES = DctLayout::PAYLOAD_BYTES;

    public function __construct(
        public Payload $payload,
        public ?SecretKey $key = null,
        // Minimum coefficient gap per block; higher survives harsher JPEG settings but is more visible.
        public float $strength = 24.0,
    ) {
        if ($payload->length() > self::MAX_PAYLOAD_BYTES) {
            throw InvalidWatermarkException::payloadTooLarge($payload->length(), self::MAX_PAYLOAD_BYTES);
        }
        ColorAdjustment::assertRange('DctWatermark', 'strength', $strength, 4, 100);
    }

    public function survivesLossyEncoding(): bool
    {
        return true;
    }

    public function area(Dimensions $image): Rectangle
    {
        return Rectangle::covering($image);
    }

    public function apply(PixelBuffer $pixels): PixelBuffer
    {
        $layout = DctLayout::for($pixels->dimensions, $this->key);
        $bits = BitString::fromBytes(DctLayout::pad(DctLayout::frame()->encode($this->payload, $this->key)));

        $bytes = $pixels->bytes;
        foreach ($layout->blocks() as $index => $block) {
            DctBlock::embed($bytes, $pixels->dimensions->width, $block, $bits[$index % DctLayout::FRAME_BITS], $this->strength);
        }

        return $pixels->withBytes($bytes);
    }
}
