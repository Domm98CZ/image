<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Drawing;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Drawing\Canvas;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\Line;
use Domm98CZ\Image\Drawing\Polygon;
use Domm98CZ\Image\Drawing\RectangleShape;
use Domm98CZ\Image\Drawing\Stroke;
use Domm98CZ\Image\Drawing\Text;
use Domm98CZ\Image\Exception\InvalidDrawingException;
use Domm98CZ\Image\Exception\InvalidFontException;
use Domm98CZ\Image\Exception\InvalidPathException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DrawingTest extends TestCase
{
    private string $fontPath;

    protected function setUp(): void
    {
        $this->fontPath = sys_get_temp_dir() . '/font-' . bin2hex(random_bytes(4)) . '.otf';
        file_put_contents($this->fontPath, 'OTTO' . str_repeat("\0", 60));
    }

    protected function tearDown(): void
    {
        foreach (glob(sys_get_temp_dir() . '/font-*') ?: [] as $file) {
            unlink($file);
        }
    }

    public function testCanvasIsImmutableAndKeepsOrder(): void
    {
        $empty = new Canvas();
        $stroke = new Stroke(Color::black(), 2);

        $drawn = $empty->line(new Point(0, 0), new Point(5, 5), $stroke)->rectangle(Rectangle::covering(new Dimensions(2, 2)), Color::white());

        self::assertSame([], $empty->shapes);
        self::assertCount(2, $drawn->shapes);
        self::assertInstanceOf(Line::class, $drawn->shapes[0]);
        self::assertInstanceOf(RectangleShape::class, $drawn->shapes[1]);
    }

    /** @return iterable<string, array{callable(): object}> */
    public static function invalidShapes(): iterable
    {
        yield 'stroke width zero' => [static fn() => new Stroke(Color::black(), 0)];
        yield 'stroke width above cap' => [static fn() => new Stroke(Color::black(), Stroke::MAX_WIDTH + 1)];
        yield 'rectangle without paint' => [static fn() => new RectangleShape(Rectangle::covering(new Dimensions(1, 1)))];
        yield 'polygon with two points' => [static fn() => new Polygon([new Point(0, 0), new Point(1, 1)], Color::black())];
        yield 'polygon without paint' => [static fn() => new Polygon([new Point(0, 0), new Point(1, 1), new Point(2, 0)])];
    }

    /** @param callable(): object $create */
    #[DataProvider('invalidShapes')]
    public function testRejectsInvalidShapes(callable $create): void
    {
        $this->expectException(InvalidDrawingException::class);

        $create();
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTexts(): iterable
    {
        yield 'empty' => [''];
        yield 'invalid utf-8' => ["abc\xC3\x28"];
        yield 'too long' => [str_repeat('a', Text::MAX_LENGTH + 1)];
    }

    #[DataProvider('invalidTexts')]
    public function testRejectsInvalidText(string $text): void
    {
        $this->expectException(InvalidDrawingException::class);

        new Text($text, new Point(0, 0), new Font($this->fontPath, 12), Color::black());
    }

    public function testTextDefaultsToNoRotationAndAcceptsMultibyte(): void
    {
        $text = new Text('Žluťoučký kůň', new Point(0, 10), new Font($this->fontPath, 12), Color::black());

        self::assertTrue($text->angle->isZero());
    }

    public function testFontValidatesFileAndSize(): void
    {
        $font = new Font($this->fontPath, 24.5);
        self::assertSame(12.0, $font->withSize(12)->size);

        $notAFont = sys_get_temp_dir() . '/font-' . bin2hex(random_bytes(4)) . '.ttf';
        file_put_contents($notAFont, '<svg/>');
        $rejected = [];
        foreach ([
            'missing' => static fn() => new Font(sys_get_temp_dir() . '/font-missing.ttf', 12),
            'not a font' => static fn() => new Font($notAFont, 12),
            'size zero' => fn() => new Font($this->fontPath, 0),
            'size above cap' => fn() => new Font($this->fontPath, 1001),
        ] as $case => $create) {
            try {
                $create();
            } catch (InvalidFontException) {
                $rejected[] = $case;
            }
        }

        self::assertSame(['missing', 'not a font', 'size zero', 'size above cap'], $rejected);
    }

    public function testFontRejectsStreamWrappers(): void
    {
        $this->expectException(InvalidPathException::class);

        new Font('https://example.com/font.ttf', 12);
    }
}
