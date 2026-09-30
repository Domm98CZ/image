<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation;

use Domm98CZ\Image\Exception\InvalidAnimationException;
use Domm98CZ\Image\Image;

// A fully composited frame: what a viewer shows for $delayMilliseconds, covering the whole canvas.
final readonly class Frame
{
    public const MAX_DELAY_MILLISECONDS = 655_350;

    public function __construct(
        public Image $image,
        public int $delayMilliseconds = 100,
    ) {
        if ($delayMilliseconds < 0 || $delayMilliseconds > self::MAX_DELAY_MILLISECONDS) {
            throw InvalidAnimationException::outOfRange('frame delay (ms)', $delayMilliseconds, '0..' . self::MAX_DELAY_MILLISECONDS);
        }
    }

    public function withImage(Image $image): self
    {
        return new self($image, $this->delayMilliseconds);
    }

    public function withDelay(int $delayMilliseconds): self
    {
        return new self($this->image, $delayMilliseconds);
    }
}
