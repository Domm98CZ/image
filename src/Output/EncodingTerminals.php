<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Output;

use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputFormats;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

// Terminal calls shared by ImageBuilder and AnimationBuilder; each one runs the whole chain once via encode().
trait EncodingTerminals
{
    abstract public function encode(OutputFormatInterface $output): EncodedImage;

    abstract private function fileWriter(): FileWriter;

    // Without an explicit output the extension picks the format; with one, a conflicting known extension is refused.
    public function save(string $path, ?OutputFormatInterface $output = null): EncodedImage
    {
        $encoded = $this->encode(OutputFormats::forPath($path, $output));
        $this->fileWriter()->write($path, $encoded);

        return $encoded;
    }

    public function toStream(StreamFactoryInterface $streams, OutputFormatInterface $output): StreamInterface
    {
        return $this->encode($output)->toStream($streams);
    }

    public function toResponse(ResponseFactoryInterface $responses, StreamFactoryInterface $streams, OutputFormatInterface $output): ResponseInterface
    {
        return $this->encode($output)->toResponse($responses, $streams);
    }

    public function toDataUri(OutputFormatInterface $output): string
    {
        return $this->encode($output)->toDataUri();
    }
}
