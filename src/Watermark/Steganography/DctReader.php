<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark\Steganography;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Image;
use Domm98CZ\Image\Watermark\SecretKey;
use Domm98CZ\Image\Watermark\WatermarkReading;

final readonly class DctReader
{
    public function read(Image|PixelBuffer $source, ?SecretKey $key = null): WatermarkReading
    {
        $pixels = $source instanceof Image ? $source->pixels() : $source;
        try {
            $layout = DctLayout::for($pixels->dimensions, $key);
        } catch (InvalidWatermarkException) {
            // Too small to have carried a DCT watermark at all.
            return WatermarkReading::absent();
        }

        // Majority vote over every block that carries the same frame bit.
        $votes = array_fill(0, DctLayout::FRAME_BITS, 0);
        foreach ($layout->blocks() as $index => $block) {
            $votes[$index % DctLayout::FRAME_BITS] += DctBlock::read($pixels->bytes, $pixels->dimensions->width, $block) === 1 ? 1 : -1;
        }
        $frameBytes = BitString::toBytes(array_values(array_map(static fn(int $vote): int => $vote > 0 ? 1 : 0, $votes)));

        $frame = DctLayout::frame();
        $header = substr($frameBytes, 0, $frame->headerLength());
        if (!$frame->isHeader($header)) {
            return WatermarkReading::absent();
        }
        $payloadLength = $frame->payloadLength($header);
        if ($payloadLength < 1 || $payloadLength > DctLayout::PAYLOAD_BYTES) {
            return WatermarkReading::absent();
        }
        $payload = substr($frameBytes, $frame->headerLength(), $payloadLength);
        $check = substr($frameBytes, $frame->headerLength() + $payloadLength, $frame->checkLength($key));

        return $frame->verify($header, $payload, $check, $key);
    }
}
