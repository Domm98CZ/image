<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark\Steganography;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Rectangle;
use Domm98CZ\Image\Watermark\Payload;
use Domm98CZ\Image\Watermark\SecretKey;

// Least significant bit of R, G and B of opaque pixels: large capacity, survives lossless re-saves only.
final readonly class LsbWatermark implements SteganographicWatermarkInterface
{
    public const MAX_PAYLOAD_BYTES = 4096;

    public function __construct(
        public Payload $payload,
        public ?SecretKey $key = null,
    ) {
        if ($payload->length() > self::MAX_PAYLOAD_BYTES) {
            throw InvalidWatermarkException::payloadTooLarge($payload->length(), self::MAX_PAYLOAD_BYTES);
        }
    }

    public static function frame(): Frame
    {
        return new Frame('DMLS', 16);
    }

    public function survivesLossyEncoding(): bool
    {
        return false;
    }

    public function area(Dimensions $image): Rectangle
    {
        return Rectangle::covering($image);
    }

    public function apply(PixelBuffer $pixels): PixelBuffer
    {
        $bits = BitString::fromBytes(self::frame()->encode($this->payload, $this->key));
        $slots = LsbSlots::for($pixels, $this->key);
        if ($slots->capacity() < count($bits)) {
            throw InvalidWatermarkException::insufficientCapacity('LSB', count($bits), $slots->capacity());
        }

        $bytes = $pixels->bytes;
        foreach ($bits as $bit) {
            $offset = $slots->next();
            $bytes[$offset] = chr((ord($bytes[$offset]) & 0xFE) | $bit);
        }

        return $pixels->withBytes($bytes);
    }
}
