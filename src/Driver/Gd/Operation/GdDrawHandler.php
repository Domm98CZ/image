<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Operation;

use Closure;
use Domm98CZ\Image\Drawing\Ellipse;
use Domm98CZ\Image\Drawing\Line;
use Domm98CZ\Image\Drawing\Polygon;
use Domm98CZ\Image\Drawing\RectangleShape;
use Domm98CZ\Image\Drawing\ShapeInterface;
use Domm98CZ\Image\Drawing\Stroke;
use Domm98CZ\Image\Drawing\Text;
use Domm98CZ\Image\Drawing\TextLayout;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\Gd\CapturedCall;
use Domm98CZ\Image\Driver\Gd\GdColor;
use Domm98CZ\Image\Driver\Gd\GdText;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\DriverOperationFailedException;
use Domm98CZ\Image\Exception\UnsupportedOperationException;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Operation\Draw;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use GdImage;

/** @implements GdOperationHandlerInterface<Draw> */
final readonly class GdDrawHandler implements GdOperationHandlerInterface
{
    // GD's fonts use points at 96 dpi; the library's font size is pixels.
    public const POINTS_PER_PIXEL = 0.75;

    public function operation(): string
    {
        return Draw::class;
    }

    // GD cannot anti-alias thick strokes or filled shapes, so it draws with visibly jagged edges.
    public function support(): Support
    {
        return Support::Degraded;
    }

    public function apply(GdImage $image, PrimitiveOperationInterface $operation): GdImage
    {
        imagealphablending($image, true);
        try {
            foreach ($operation->shapes as $shape) {
                $this->draw($image, $shape);
            }
        } finally {
            imagesetthickness($image, 1);
            imageantialias($image, false);
            imagealphablending($image, false);
        }

        return $image;
    }

    private function draw(GdImage $image, ShapeInterface $shape): void
    {
        match (true) {
            $shape instanceof Line => $this->stroke($image, $shape->stroke, static fn(int $color): bool => imageline($image, $shape->from->x, $shape->from->y, $shape->to->x, $shape->to->y, $color)),
            $shape instanceof RectangleShape => $this->rectangle($image, $shape),
            $shape instanceof Ellipse => $this->ellipse($image, $shape),
            $shape instanceof Polygon => $this->polygon($image, $shape),
            $shape instanceof Text => $this->text($image, $shape),
            default => throw UnsupportedOperationException::noDriverSupports($shape::class, [DriverName::Gd]),
        };
    }

    private function rectangle(GdImage $image, RectangleShape $shape): void
    {
        $area = $shape->area;
        [$left, $top, $right, $bottom] = [$area->left(), $area->top(), $area->rightExclusive() - 1, $area->bottomExclusive() - 1];
        if ($shape->fill !== null) {
            imagefilledrectangle($image, $left, $top, $right, $bottom, GdColor::allocate($image, $shape->fill));
        }
        if ($shape->stroke !== null) {
            $this->stroke($image, $shape->stroke, static fn(int $color): bool => imagerectangle($image, $left, $top, $right, $bottom, $color));
        }
    }

    private function ellipse(GdImage $image, Ellipse $shape): void
    {
        [$x, $y, $width, $height] = [$shape->center->x, $shape->center->y, $shape->size->width, $shape->size->height];
        if ($shape->fill !== null) {
            imagefilledellipse($image, $x, $y, $width, $height, GdColor::allocate($image, $shape->fill));
        }
        if ($shape->stroke !== null) {
            // imageellipse ignores thickness; a full arc honours it.
            $this->stroke($image, $shape->stroke, static fn(int $color): bool => imagearc($image, $x, $y, $width, $height, 0, 360, $color));
        }
    }

    private function polygon(GdImage $image, Polygon $shape): void
    {
        $points = array_merge(...array_map(static fn(Point $point): array => [$point->x, $point->y], $shape->points));
        if ($shape->fill !== null) {
            imagefilledpolygon($image, $points, GdColor::allocate($image, $shape->fill));
        }
        if ($shape->stroke !== null) {
            $this->stroke($image, $shape->stroke, static fn(int $color): bool => imagepolygon($image, $points, $color));
        }
    }

    // Lines are drawn one by one: libgd's own "\n" handling would stack them at its 1.05 em, not the library's pitch.
    private function text(GdImage $image, Text $shape): void
    {
        foreach (TextLayout::shapes($shape) as $line) {
            $this->line($image, $line);
        }
    }

    private function line(GdImage $image, Text $line): void
    {
        $call = CapturedCall::run(static fn(): array|false => imagettftext(
            $image,
            $line->font->size * self::POINTS_PER_PIXEL,
            // GD rotates text counter-clockwise.
            fmod(360.0 - $line->angle->clockwiseDegrees, 360.0),
            $line->baseline->x,
            $line->baseline->y,
            GdColor::allocate($image, $line->color),
            $line->font->path,
            GdText::encode($line->text, $line->font),
        ));
        if ($call->result === false) {
            throw DriverOperationFailedException::because(DriverName::Gd, 'draw text', $call->error);
        }
    }

    /** @param Closure(int): bool $draw */
    private function stroke(GdImage $image, Stroke $stroke, Closure $draw): void
    {
        imagesetthickness($image, $stroke->width);
        // GD anti-aliases only hairlines; wider strokes stay aliased (hence Support::Degraded).
        imageantialias($image, $stroke->width === 1 && $stroke->color->isOpaque());
        $draw(GdColor::allocate($image, $stroke->color));
    }
}
