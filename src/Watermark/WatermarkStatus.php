<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark;

enum WatermarkStatus
{
    // No watermark found (or it was embedded with a different key).
    case Absent;
    // Found, unkeyed, and its checksum matches: undamaged, but anyone could have written it.
    case Intact;
    // Found and its HMAC verifies with the given key.
    case Authentic;
    // Found, but the checksum or HMAC does not match: damaged or forged.
    case Tampered;
    // Found, but signed differently than the reader asked for (keyed without a key given, or the reverse).
    case Unverified;
}
