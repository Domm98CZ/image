<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Security;

use Domm98CZ\Image\Format\Header\ImageHeader;

/** @internal Created by InputGuard only; holding one means the bytes passed every boundary check. */
final readonly class ValidatedInput
{
    public function __construct(
        public string $bytes,
        public ImageHeader $header,
    ) {}
}
