<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Security;

enum LimitType: string
{
    case Width = 'width';
    case Height = 'height';
    case Pixels = 'pixels';
    case Frames = 'frames';
    case AnimationPixels = 'animation_pixels';
    case InputBytes = 'input_bytes';
    case Memory = 'memory';

    public function description(): string
    {
        return match ($this) {
            self::Width => 'image width in pixels',
            self::Height => 'image height in pixels',
            self::Pixels => 'total pixel count',
            self::Frames => 'animation frame count',
            self::AnimationPixels => 'total pixel count across all animation frames',
            self::InputBytes => 'input size in bytes',
            self::Memory => 'estimated decode memory in bytes',
        };
    }
}
