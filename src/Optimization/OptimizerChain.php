<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Optimization;

use Domm98CZ\Image\Format\EncodedImage;

final readonly class OptimizerChain implements OptimizerInterface
{
    /** @param list<OptimizerInterface> $optimizers */
    public function __construct(
        private array $optimizers,
    ) {}

    public function optimize(EncodedImage $image): EncodedImage
    {
        foreach ($this->optimizers as $optimizer) {
            $image = $optimizer->optimize($image);
        }

        return $image;
    }
}
