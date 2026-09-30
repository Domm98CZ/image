<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Operation;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Drawing\Ellipse;
use Domm98CZ\Image\Drawing\FontCoverage;
use Domm98CZ\Image\Drawing\Line;
use Domm98CZ\Image\Drawing\Polygon;
use Domm98CZ\Image\Drawing\RectangleShape;
use Domm98CZ\Image\Drawing\ShapeInterface;
use Domm98CZ\Image\Drawing\Stroke;
use Domm98CZ\Image\Drawing\Text;
use Domm98CZ\Image\Drawing\TextLayout;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\Imagick\ImagickColor;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\UnsupportedOperationException;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Operation\Draw;
use Domm98CZ\Image\Operation\PrimitiveOperationInterface;
use Imagick;
use ImagickDraw;

/** @implements ImagickOperationHandlerInterface<Draw> */
final readonly class ImagickDrawHandler implements ImagickOperationHandlerInterface
{
    public function operation(): string
    {
        return Draw::class;
    }

    public function support(): Support
    {
        return Support::Native;
    }

    public function apply(Imagick $image, PrimitiveOperationInterface $operation): Imagick
    {
        foreach ($operation->shapes as $shape) {
            $this->draw($image, $shape);
        }

        return $image;
    }

    private function draw(Imagick $image, ShapeInterface $shape): void
    {
        if ($shape instanceof Text) {
            $draw = new ImagickDraw();
            $draw->setFont($shape->font->path);
            $draw->setFontSize($shape->font->size);
            $draw->setFillColor(ImagickColor::toPixel($shape->color));
            $draw->setTextAntialias(true);
            // Lines are drawn one by one: annotateImage's own "\n" handling would stack them by the font's metrics, not the library's pitch.
            foreach (TextLayout::shapes($shape) as $line) {
                // annotateImage renders the string literally ("%" and "@file" are not expanded; covered by tests).
                $image->annotateImage($draw, $line->baseline->x, $line->baseline->y, $line->angle->clockwiseDegrees, FontCoverage::of($line->font)->substitute($line->text));
            }

            return;
        }

        $draw = match (true) {
            $shape instanceof Line => self::paint(null, $shape->stroke),
            $shape instanceof RectangleShape, $shape instanceof Ellipse, $shape instanceof Polygon => self::paint($shape->fill, $shape->stroke),
            default => throw UnsupportedOperationException::noDriverSupports($shape::class, [DriverName::Imagick]),
        };
        match (true) {
            $shape instanceof Line => $draw->line($shape->from->x, $shape->from->y, $shape->to->x, $shape->to->y),
            $shape instanceof RectangleShape => $draw->rectangle($shape->area->left(), $shape->area->top(), $shape->area->rightExclusive() - 1, $shape->area->bottomExclusive() - 1),
            $shape instanceof Ellipse => $draw->ellipse($shape->center->x, $shape->center->y, $shape->size->width / 2, $shape->size->height / 2, 0, 360),
            $shape instanceof Polygon => $draw->polygon(array_map(static fn(Point $point): array => ['x' => $point->x, 'y' => $point->y], $shape->points)),
        };
        $image->drawImage($draw);
    }

    private static function paint(?Color $fill, ?Stroke $stroke): ImagickDraw
    {
        $draw = new ImagickDraw();
        $draw->setFillColor($fill === null ? 'none' : ImagickColor::toPixel($fill));
        $draw->setStrokeColor($stroke === null ? 'none' : ImagickColor::toPixel($stroke->color));
        $draw->setStrokeWidth($stroke === null ? 0 : $stroke->width);
        $draw->setStrokeAntialias(true);

        return $draw;
    }
}
