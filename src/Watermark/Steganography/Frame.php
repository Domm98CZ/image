<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark\Steganography;

use Domm98CZ\Image\Watermark\Payload;
use Domm98CZ\Image\Watermark\SecretKey;
use Domm98CZ\Image\Watermark\WatermarkReading;
use Domm98CZ\Image\Watermark\WatermarkStatus;

/** @internal Framing shared by the steganographic methods: header, payload, then CRC32 or truncated HMAC. */
final readonly class Frame
{
    private const FLAG_KEYED = 0x01;

    public function __construct(
        private string $magic,
        private int $keyedCheckBytes,
    ) {}

    public function headerLength(): int
    {
        return strlen($this->magic) + 3;
    }

    public function checkLength(?SecretKey $key): int
    {
        return $key === null ? 4 : $this->keyedCheckBytes;
    }

    public function encode(Payload $payload, ?SecretKey $key): string
    {
        $header = $this->magic . chr($key === null ? 0 : self::FLAG_KEYED) . pack('n', $payload->length());

        return $header . $payload->bytes . $this->check($header . $payload->bytes, $key);
    }

    public function isHeader(string $header): bool
    {
        return strlen($header) === $this->headerLength() && str_starts_with($header, $this->magic);
    }

    public function payloadLength(string $header): int
    {
        $offset = strlen($this->magic) + 1;

        return (ord($header[$offset]) << 8) | ord($header[$offset + 1]);
    }

    public function verify(string $header, string $payload, string $check, ?SecretKey $key): WatermarkReading
    {
        $keyed = (ord($header[strlen($this->magic)]) & self::FLAG_KEYED) !== 0;
        if ($keyed !== ($key !== null) || $payload === '') {
            return WatermarkReading::absent();
        }
        $valid = hash_equals($this->check($header . $payload, $key), $check);

        return new WatermarkReading(
            $valid ? ($key === null ? WatermarkStatus::Intact : WatermarkStatus::Authentic) : WatermarkStatus::Tampered,
            new Payload($payload),
        );
    }

    private function check(string $data, ?SecretKey $key): string
    {
        return $key === null
            ? pack('N', crc32($data))
            : substr($key->derive('watermark-check/' . bin2hex($this->magic), $data), 0, $this->keyedCheckBytes);
    }
}
