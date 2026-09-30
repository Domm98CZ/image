<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Pipeline;

use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\ImageHandleInterface;
use Domm98CZ\Image\Format\Output\PngOutput;
use Domm98CZ\Image\Security\InputGuard;

// Lossless hand-off between drivers through a fast PNG (measured ~0.5 s per 12 MP in either direction).
final readonly class Transfer
{
    private const COMPRESSION_LEVEL = 1;

    public function __construct(
        private InputGuard $guard,
    ) {}

    public function move(ImageHandleInterface $image, DriverInterface $from, DriverInterface $to): ImageHandleInterface
    {
        // The bytes are never stored, so scanning for opacity would only cost time.
        $encoded = $from->encode($image, new PngOutput(self::COMPRESSION_LEVEL, keepAlphaChannel: true));

        return $to->decode($this->guard->inspectHandoff($encoded->bytes));
    }
}
