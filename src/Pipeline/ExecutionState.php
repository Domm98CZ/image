<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Pipeline;

use Domm98CZ\Image\Driver\ImageHandleInterface;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Metadata\Orientation;

/** @internal */
final readonly class ExecutionState
{
    public function __construct(
        public ImageHandleInterface $handle,
        public Orientation $orientation,
        public ?FormatName $sourceFormat,
    ) {}

    public function withHandle(ImageHandleInterface $handle): self
    {
        return new self($handle, $this->orientation, $this->sourceFormat);
    }

    public function withOrientation(Orientation $orientation): self
    {
        return new self($this->handle, $orientation, $this->sourceFormat);
    }
}
