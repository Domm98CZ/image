<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver;

enum Support
{
    case Native;
    case PhpFallback;
    // Works, but loses something (e.g. an encoder that drops alpha); used only when nothing better exists.
    case Degraded;
    case None;

    public function isSupported(): bool
    {
        return $this !== self::None;
    }

    public function worse(self $other): self
    {
        return $other->rank() > $this->rank() ? $other : $this;
    }

    private function rank(): int
    {
        return match ($this) {
            self::Native => 0,
            self::PhpFallback => 1,
            self::Degraded => 2,
            self::None => 3,
        };
    }
}
