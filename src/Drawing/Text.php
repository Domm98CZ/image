<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Drawing;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Exception\InvalidDrawingException;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Point;

final readonly class Text implements ShapeInterface
{
    public const MAX_LENGTH = 10_000;

    public Angle $angle;

    public function __construct(
        public string $text,
        // Left end of the first line's baseline, the anchor both GD and ImageMagick use natively.
        public Point $baseline,
        public Font $font,
        public Color $color,
        ?Angle $angle = null,
    ) {
        $this->angle = $angle ?? Angle::clockwise(0);
        if ($text === '') {
            throw InvalidDrawingException::invalidText('must not be empty');
        }
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw InvalidDrawingException::invalidText('must be valid UTF-8');
        }
        if (mb_strlen($text, 'UTF-8') > self::MAX_LENGTH) {
            throw InvalidDrawingException::invalidText(sprintf('must be at most %d characters', self::MAX_LENGTH));
        }
    }
}
