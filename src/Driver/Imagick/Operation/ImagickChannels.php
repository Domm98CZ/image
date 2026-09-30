<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Imagick;

/** @internal */
final class ImagickChannels
{
    // Color operations must leave alpha alone, and ImageMagick 7's default channel set includes it.
    public const RGB = Imagick::CHANNEL_RED | Imagick::CHANNEL_GREEN | Imagick::CHANNEL_BLUE;
}
