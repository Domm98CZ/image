<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Security;

use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Geometry\Dimensions;

final readonly class LimitChecker
{
    public function __construct(
        private Limits $limits,
    ) {}

    public function checkDimensions(Dimensions $dimensions): void
    {
        $this->check(LimitType::Width, $dimensions->width, $this->limits->maxWidth);
        $this->check(LimitType::Height, $dimensions->height, $this->limits->maxHeight);
        $this->check(LimitType::Pixels, $dimensions->pixelCount(), $this->limits->maxPixels);
    }

    public function checkAnimation(int $frameCount, Dimensions $frame): void
    {
        $this->check(LimitType::Frames, $frameCount, $this->limits->maxFrames);
        $this->check(LimitType::AnimationPixels, $frameCount * $frame->pixelCount(), $this->limits->maxAnimationPixels);
    }

    public function check(LimitType $limit, int $actual, int $allowed): void
    {
        if ($actual > $allowed) {
            throw new LimitExceededException(new LimitViolation($limit, $actual, $allowed));
        }
    }
}
