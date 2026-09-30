<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Animation;

use Domm98CZ\Image\Animation\Gif\GifCanvas;
use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use PHPUnit\Framework\TestCase;

final class GifCanvasTest extends TestCase
{
    public function testStartsTransparent(): void
    {
        self::assertTrue((new GifCanvas(new Dimensions(3, 2)))->pixels()->colorAt(new Point(2, 1))->isFullyTransparent());
    }

    public function testFrameCopiesOnlyItsOpaquePixelsAndIsClippedToTheScreen(): void
    {
        $red = Color::fromHex('#ff0000');
        $base = (new GifCanvas(new Dimensions(4, 4)))->withFrame(PixelBuffer::filled(new Dimensions(4, 4), $red), Point::origin());
        $frame = new PixelBuffer(new Dimensions(2, 2), "\x00\x00\xFF\xFF" . "\x00\x00\x00\x00" . "\x00\xFF\x00\xFF" . "\x00\x00\xFF\xFF");

        $canvas = $base->withFrame($frame, new Point(3, 2))->pixels();

        self::assertSame('#0000ff', $canvas->colorAt(new Point(3, 2))->toHex());
        self::assertSame('#00ff00', $canvas->colorAt(new Point(3, 3))->toHex());
        self::assertSame('#ff0000', $canvas->colorAt(new Point(2, 2))->toHex());
    }

    public function testFrameEntirelyOutsideTheScreenChangesNothing(): void
    {
        $canvas = (new GifCanvas(new Dimensions(2, 2)))->withFrame(PixelBuffer::filled(new Dimensions(2, 2), Color::black()), new Point(5, 5));

        self::assertTrue($canvas->pixels()->colorAt(new Point(1, 1))->isFullyTransparent());
    }

    public function testClearedAreaBecomesTransparent(): void
    {
        $canvas = (new GifCanvas(new Dimensions(4, 4)))
            ->withFrame(PixelBuffer::filled(new Dimensions(4, 4), Color::white()), Point::origin())
            ->clearedArea(new Rectangle(new Point(2, 2), new Dimensions(5, 5)))
            ->pixels();

        self::assertTrue($canvas->colorAt(new Point(3, 3))->isFullyTransparent());
        self::assertTrue($canvas->colorAt(new Point(1, 3))->isOpaque());
    }

    public function testReplacedCanvasIsMaterializedOnlyWhenNeeded(): void
    {
        $calls = 0;
        $canvas = (new GifCanvas(new Dimensions(1, 1)))->replacedBy(static function () use (&$calls): PixelBuffer {
            ++$calls;

            return PixelBuffer::filled(new Dimensions(1, 1), Color::white());
        });

        self::assertSame(0, $calls);
        self::assertTrue($canvas->pixels()->colorAt(Point::origin())->equals(Color::white()));
        self::assertSame(1, $calls);
    }
}
