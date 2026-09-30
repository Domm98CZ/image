<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation\Gif;

// What happens to a frame's area before the next frame is drawn (GIF89a Graphic Control Extension).
enum GifDisposal: int
{
    case Unspecified = 0;
    case Keep = 1;
    case RestoreBackground = 2;
    case RestorePrevious = 3;

    public static function fromPackedFields(int $packed): self
    {
        // Values 4-7 are reserved; decoders treat them like "keep".
        return self::tryFrom(($packed >> 2) & 0x07) ?? self::Keep;
    }
}
