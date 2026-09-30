<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation\Gif;

/** @internal */
final readonly class GifFrameSource
{
    public function __construct(
        public string $gif,
        public int $delayCentiseconds,
    ) {}
}
