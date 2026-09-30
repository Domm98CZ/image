<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final readonly class EncodedImage
{
    public function __construct(
        public string $bytes,
        public FormatName $format,
    ) {}

    public function byteCount(): int
    {
        return strlen($this->bytes);
    }

    public function mimeType(): string
    {
        return $this->format->mimeType();
    }

    public function fileExtension(): string
    {
        return $this->format->fileExtension();
    }

    public function toDataUri(): string
    {
        return sprintf('data:%s;base64,%s', $this->mimeType(), base64_encode($this->bytes));
    }

    // The caller's PSR-17 factory keeps this library free of any concrete PSR-7 implementation.
    public function toStream(StreamFactoryInterface $streams): StreamInterface
    {
        $stream = $streams->createStream($this->bytes);
        // PSR-17 does not say where the pointer ends up; a consumer reading from "here" must get the whole image.
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $stream;
    }

    public function toResponse(ResponseFactoryInterface $responses, StreamFactoryInterface $streams, int $status = 200): ResponseInterface
    {
        return $responses->createResponse($status)
            ->withHeader('Content-Type', $this->mimeType())
            ->withHeader('Content-Length', (string) $this->byteCount())
            // The type is known from encoding; forbid browsers from guessing another one.
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withBody($this->toStream($streams));
    }
}
