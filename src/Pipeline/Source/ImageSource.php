<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Pipeline\Source;

use Domm98CZ\Image\Image;

final readonly class ImageSource implements SourceInterface
{
    public function __construct(
        public Image $image,
    ) {}
}
