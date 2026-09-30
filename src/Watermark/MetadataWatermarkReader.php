<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark;

use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Metadata\MetadataExtractor;
use Domm98CZ\Image\Metadata\XmpPacket;

final readonly class MetadataWatermarkReader
{
    public function read(EncodedImage $image, ?SecretKey $key = null): WatermarkReading
    {
        $xmp = (new MetadataExtractor())->extract($image)->xmp;
        $packet = $xmp === null ? null : XmpPacket::parse($xmp);
        if ($packet === null || $packet->payload === null) {
            return WatermarkReading::absent();
        }
        $bytes = base64_decode($packet->payload, true);
        $signature = $packet->signature === null ? false : base64_decode($packet->signature, true);
        if ($bytes === false || $bytes === '' || $signature === false) {
            return new WatermarkReading(WatermarkStatus::Tampered);
        }
        $expectedScheme = $key === null ? MetadataWatermark::SCHEME_CRC32 : MetadataWatermark::SCHEME_HMAC;
        if ($packet->scheme !== $expectedScheme) {
            return new WatermarkReading(WatermarkStatus::Unverified, new Payload($bytes));
        }
        $payload = new Payload($bytes);
        $valid = hash_equals(MetadataWatermark::signature($payload, $key), $signature);

        return new WatermarkReading($valid ? ($key === null ? WatermarkStatus::Intact : WatermarkStatus::Authentic) : WatermarkStatus::Tampered, $payload);
    }
}
