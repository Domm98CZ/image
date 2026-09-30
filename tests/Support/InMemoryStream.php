<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Support;

use Closure;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class InMemoryStream implements StreamInterface
{
    private int $position = 0;

    /** @param Closure(int): string|null $readOverride */
    public function __construct(
        private readonly string $contents,
        private readonly bool $readable = true,
        private readonly bool $seekable = true,
        private readonly ?int $reportedSize = null,
        private readonly ?Closure $readOverride = null,
    ) {}

    public static function positionedAtEnd(string $contents): self
    {
        $stream = new self($contents);
        $stream->seek(strlen($contents));

        return $stream;
    }

    public function __toString(): string
    {
        return $this->contents;
    }

    public function close(): void {}

    public function detach()
    {
        return null;
    }

    public function getSize(): ?int
    {
        return $this->reportedSize ?? strlen($this->contents);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->contents);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->seekable) {
            throw new RuntimeException('Stream is not seekable.');
        }
        $this->position = $offset;
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('Stream is not writable.');
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function read(int $length): string
    {
        if ($this->readOverride !== null) {
            return ($this->readOverride)($length);
        }
        $chunk = substr($this->contents, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function getContents(): string
    {
        return $this->read(PHP_INT_MAX);
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }
}
