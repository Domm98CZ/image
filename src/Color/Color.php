<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Color;

use Domm98CZ\Image\Exception\InvalidColorException;

final readonly class Color
{
    public const OPAQUE = 255;
    public const TRANSPARENT = 0;

    public function __construct(
        public int $red,
        public int $green,
        public int $blue,
        public int $alpha = self::OPAQUE,
    ) {
        self::assertChannel('red', $red);
        self::assertChannel('green', $green);
        self::assertChannel('blue', $blue);
        self::assertChannel('alpha', $alpha);
    }

    public static function rgb(int $red, int $green, int $blue): self
    {
        return new self($red, $green, $blue);
    }

    public static function rgba(int $red, int $green, int $blue, float $opacity): self
    {
        return (new self($red, $green, $blue))->withOpacity($opacity);
    }

    public static function fromHex(string $hex): self
    {
        if (preg_match('/^#?([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/iD', $hex, $matches) !== 1) {
            throw InvalidColorException::malformedHex($hex);
        }
        $digits = $matches[1];
        if (strlen($digits) <= 4) {
            $digits = implode('', array_map(static fn(string $digit): string => $digit . $digit, str_split($digits)));
        }
        $bytes = array_map(hexdec(...), str_split($digits, 2));

        return new self((int) $bytes[0], (int) $bytes[1], (int) $bytes[2], (int) ($bytes[3] ?? self::OPAQUE));
    }

    public static function black(): self
    {
        return new self(0, 0, 0);
    }

    public static function white(): self
    {
        return new self(255, 255, 255);
    }

    public static function transparent(): self
    {
        return new self(0, 0, 0, self::TRANSPARENT);
    }

    public function withAlpha(int $alpha): self
    {
        return new self($this->red, $this->green, $this->blue, $alpha);
    }

    public function withOpacity(float $opacity): self
    {
        if (!is_finite($opacity) || $opacity < 0.0 || $opacity > 1.0) {
            throw InvalidColorException::channelOutOfRange('opacity', $opacity, '0.0-1.0');
        }

        return $this->withAlpha((int) round($opacity * self::OPAQUE));
    }

    public function opacity(): float
    {
        return $this->alpha / self::OPAQUE;
    }

    public function isOpaque(): bool
    {
        return $this->alpha === self::OPAQUE;
    }

    public function isFullyTransparent(): bool
    {
        return $this->alpha === self::TRANSPARENT;
    }

    public function toHex(): string
    {
        $hex = sprintf('#%02x%02x%02x', $this->red, $this->green, $this->blue);

        return $this->isOpaque() ? $hex : $hex . sprintf('%02x', $this->alpha);
    }

    public function equals(self $other): bool
    {
        return $this->red === $other->red
            && $this->green === $other->green
            && $this->blue === $other->blue
            && $this->alpha === $other->alpha;
    }

    private static function assertChannel(string $channel, int $value): void
    {
        if ($value < 0 || $value > 255) {
            throw InvalidColorException::channelOutOfRange($channel, $value, '0-255');
        }
    }
}
