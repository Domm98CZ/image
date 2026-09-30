<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark;

final readonly class WatermarkReading
{
    public function __construct(
        public WatermarkStatus $status,
        public ?Payload $payload = null,
    ) {}

    public static function absent(): self
    {
        return new self(WatermarkStatus::Absent);
    }

    public function isTrusted(): bool
    {
        return $this->status === WatermarkStatus::Authentic;
    }
}
