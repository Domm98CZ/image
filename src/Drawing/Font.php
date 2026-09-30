<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Drawing;

use Domm98CZ\Image\Exception\InvalidFontException;
use Domm98CZ\Image\Security\LocalPath;

final readonly class Font
{
    private const SIGNATURES = ["\x00\x01\x00\x00", 'OTTO', 'true', 'ttcf'];
    private const MAX_SIZE = 1000.0;

    // Size is the em size in pixels (drivers convert: GD works in points at 96 dpi, ImageMagick in pixels).
    public function __construct(
        public string $path,
        public float $size,
    ) {
        LocalPath::assertValid($path);
        if (!is_file($path) || !is_readable($path)) {
            throw InvalidFontException::notReadable($path);
        }
        $signature = file_get_contents($path, false, null, 0, 4);
        if (!in_array($signature, self::SIGNATURES, true)) {
            throw InvalidFontException::notAFont($path);
        }
        if (!is_finite($size) || $size < 1.0 || $size > self::MAX_SIZE) {
            throw InvalidFontException::invalidSize($size);
        }
    }

    public function withSize(float $size): self
    {
        return new self($this->path, $size);
    }
}
