<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Color\Profile;

use Domm98CZ\Image\Exception\InvalidColorException;

// The per-channel transfer function of a matrix/TRC RGB profile, in ICC curveType form.
final readonly class ToneCurve
{
    private const SRGB_TABLE_SIZE = 1024;

    /** @param list<int> $table 16-bit samples; empty means a pure gamma curve. */
    private function __construct(
        public float $gamma,
        private array $table,
    ) {}

    public static function gamma(float $gamma): self
    {
        // u8Fixed8Number: the largest encodable gamma is just under 256.
        if (!is_finite($gamma) || $gamma < 0.1 || $gamma > 10.0) {
            throw InvalidColorException::profileValueOutOfRange('gamma', $gamma, '0.1..10');
        }

        return new self($gamma, []);
    }

    // IEC 61966-2-1: linear toe below 0.04045, 2.4 power above.
    public static function srgb(): self
    {
        $table = [];
        for ($index = 0; $index < self::SRGB_TABLE_SIZE; ++$index) {
            $encoded = $index / (self::SRGB_TABLE_SIZE - 1);
            $linear = $encoded <= 0.04045 ? $encoded / 12.92 : (($encoded + 0.055) / 1.055) ** 2.4;
            $table[] = (int) round($linear * 65535);
        }

        return new self(2.2, $table);
    }

    public function curveTag(): string
    {
        if ($this->table === []) {
            return 'curv' . "\0\0\0\0" . pack('N', 1) . pack('n', (int) round($this->gamma * 256));
        }

        return 'curv' . "\0\0\0\0" . pack('N', count($this->table)) . pack('n*', ...$this->table);
    }
}
