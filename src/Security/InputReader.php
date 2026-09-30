<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Security;

use Domm98CZ\Image\Exception\InputReadException;
use Domm98CZ\Image\Exception\LimitExceededException;
use Psr\Http\Message\StreamInterface;
use Throwable;

final readonly class InputReader
{
    private const STREAM_CHUNK_BYTES = 65_536;

    public function __construct(
        private Limits $limits,
    ) {}

    public function fromBytes(string $bytes): string
    {
        $this->assertWithinLimit(strlen($bytes));

        return $bytes;
    }

    public function fromFile(string $path): string
    {
        LocalPath::assertValid($path);
        if (!is_file($path) || !is_readable($path)) {
            throw InputReadException::fileNotReadable($path);
        }

        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        try {
            // Reading one byte past the limit detects oversize input without trusting filesize().
            $bytes = file_get_contents($path, false, null, 0, max(0, $this->limits->maxInputBytes) + 1);
        } finally {
            restore_error_handler();
        }
        if ($bytes === false) {
            throw InputReadException::fileNotReadable($path, $error);
        }
        $this->assertWithinLimit(strlen($bytes));

        return $bytes;
    }

    public function fromStream(StreamInterface $stream): string
    {
        if (!$stream->isReadable()) {
            throw InputReadException::streamNotReadable();
        }

        $chunks = [];
        $total = 0;
        try {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            while (!$stream->eof()) {
                $chunk = $stream->read(self::STREAM_CHUNK_BYTES);
                if ($chunk === '') {
                    break;
                }
                $total += strlen($chunk);
                // getSize() may be unknown or wrong, so the cap is enforced on bytes actually read.
                $this->assertWithinLimit($total);
                $chunks[] = $chunk;
            }
        } catch (LimitExceededException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw InputReadException::streamFailed($exception);
        }

        return implode('', $chunks);
    }

    private function assertWithinLimit(int $bytes): void
    {
        if ($bytes > $this->limits->maxInputBytes) {
            throw new LimitExceededException(new LimitViolation(LimitType::InputBytes, $bytes, $this->limits->maxInputBytes));
        }
    }
}
