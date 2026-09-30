<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark\Steganography;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\Watermark\SecretKey;
use Domm98CZ\Image\Watermark\WatermarkReading;

final readonly class LsbReader
{
    public function read(Image|PixelBuffer $source, ?SecretKey $key = null): WatermarkReading
    {
        $pixels = $source instanceof Image ? $source->pixels() : $source;
        $frame = LsbWatermark::frame();
        $slots = LsbSlots::for($pixels, $key);
        $capacityBytes = intdiv($slots->capacity(), 8);

        if ($capacityBytes < $frame->headerLength()) {
            return WatermarkReading::absent();
        }
        $header = self::readBytes($pixels, $slots, $frame->headerLength());
        if (!$frame->isHeader($header)) {
            return WatermarkReading::absent();
        }
        $payloadLength = $frame->payloadLength($header);
        $remaining = $payloadLength + $frame->checkLength($key);
        if ($payloadLength > LsbWatermark::MAX_PAYLOAD_BYTES || $frame->headerLength() + $remaining > $capacityBytes) {
            return WatermarkReading::absent();
        }
        $payload = self::readBytes($pixels, $slots, $payloadLength);
        $check = self::readBytes($pixels, $slots, $frame->checkLength($key));

        return $frame->verify($header, $payload, $check, $key);
    }

    private static function readBytes(PixelBuffer $pixels, LsbSlots $slots, int $count): string
    {
        $bits = [];
        for ($index = 0; $index < $count * 8; ++$index) {
            $bits[] = ord($pixels->bytes[$slots->next()]) & 1;
        }

        return BitString::toBytes($bits);
    }
}
