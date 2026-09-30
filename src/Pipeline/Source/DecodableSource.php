<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Pipeline\Source;

use Domm98CZ\Image\Color\Profile\ColorProfile;
use Domm98CZ\Image\Metadata\Orientation;
use Domm98CZ\Image\Security\ValidatedInput;

final readonly class DecodableSource implements SourceInterface
{
    public function __construct(
        public ValidatedInput $input,
        public Orientation $orientation,
        public ?ColorProfile $colorProfile = null,
    ) {}

    public function needsColorManagement(): bool
    {
        return $this->colorProfile?->needsConversion() ?? false;
    }
}
