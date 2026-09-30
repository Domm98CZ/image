<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Binary;

use Domm98CZ\Image\Exception\CorruptedImageException;

final readonly class BinaryString
{
    public function __construct(
        public string $bytes,
    ) {}

    public function length(): int
    {
        return strlen($this->bytes);
    }

    public function has(int $offset, int $length): bool
    {
        return $offset >= 0 && $length >= 0 && $offset <= strlen($this->bytes) - $length;
    }

    public function slice(int $offset, int $length): string
    {
        $this->assertAvailable($offset, $length);

        return substr($this->bytes, $offset, $length);
    }

    public function matchesAt(int $offset, string $expected): bool
    {
        return $this->has($offset, strlen($expected)) && substr_compare($this->bytes, $expected, $offset, strlen($expected)) === 0;
    }

    public function uint8(int $offset): int
    {
        $this->assertAvailable($offset, 1);

        return ord($this->bytes[$offset]);
    }

    public function uint16BigEndian(int $offset): int
    {
        return $this->unpack('n', $offset, 2);
    }

    public function uint16LittleEndian(int $offset): int
    {
        return $this->unpack('v', $offset, 2);
    }

    public function uint24LittleEndian(int $offset): int
    {
        return $this->uint16LittleEndian($offset) | ($this->uint8($offset + 2) << 16);
    }

    public function uint32BigEndian(int $offset): int
    {
        return $this->unpack('N', $offset, 4);
    }

    public function uint32LittleEndian(int $offset): int
    {
        return $this->unpack('V', $offset, 4);
    }

    public function uint64BigEndian(int $offset): int
    {
        $value = $this->unpack('J', $offset, 8);
        if ($value < 0) {
            // Above PHP_INT_MAX: no real image has boxes this large.
            throw CorruptedImageException::truncated($offset, PHP_INT_MAX, $this->length());
        }

        return $value;
    }

    private function unpack(string $format, int $offset, int $length): int
    {
        $this->assertAvailable($offset, $length);
        /** @var array{1: int} $values */
        $values = unpack($format, $this->bytes, $offset);

        return $values[1];
    }

    private function assertAvailable(int $offset, int $length): void
    {
        if (!$this->has($offset, $length)) {
            throw CorruptedImageException::truncated($offset, $length, $this->length());
        }
    }
}
