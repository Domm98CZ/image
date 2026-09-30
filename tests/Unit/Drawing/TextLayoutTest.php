<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Drawing;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\Text;
use Domm98CZ\Image\Drawing\TextLayout;
use Domm98CZ\Image\Geometry\Angle;
use Domm98CZ\Image\Geometry\Point;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextLayoutTest extends TestCase
{
    private string $fontPath;

    protected function setUp(): void
    {
        $this->fontPath = sys_get_temp_dir() . '/layout-font-' . bin2hex(random_bytes(4)) . '.otf';
        file_put_contents($this->fontPath, 'OTTO' . str_repeat("\0", 60));
    }

    protected function tearDown(): void
    {
        @unlink($this->fontPath);
    }

    public function testPitchIsAFixedMultipleOfTheFontSizeWhateverTheFont(): void
    {
        self::assertSame(1.2, TextLayout::LINE_HEIGHT);
        self::assertSame(48.0, TextLayout::pitch(new Font($this->fontPath, 40)));
        self::assertSame(24.0, TextLayout::pitch(new Font($this->fontPath, 20)));
    }

    public function testASingleLineIsTheShapeItself(): void
    {
        $text = new Text('Hello', new Point(10, 60), new Font($this->fontPath, 40), Color::black());

        self::assertSame([$text], TextLayout::shapes($text));
        self::assertSame(['Hello'], TextLayout::lines('Hello'));
    }

    public function testLinesAreStackedOnePitchApartFromTheFirstBaseline(): void
    {
        $font = new Font($this->fontPath, 28);
        $text = new Text("one\ntwo\nthree", new Point(10, 60), $font, Color::black());

        $lines = TextLayout::shapes($text);

        self::assertSame(['one', 'two', 'three'], array_map(static fn(Text $line): string => $line->text, $lines));
        // 33.6 px per line, rounded from the first baseline rather than accumulated: 0, 34, 67.
        self::assertSame([[10, 60], [10, 94], [10, 127]], array_map(static fn(Text $line): array => [$line->baseline->x, $line->baseline->y], $lines));
        foreach ($lines as $line) {
            self::assertSame($font, $line->font);
            self::assertTrue($line->angle->isZero());
        }
    }

    public function testABlankLineDrawsNothingButKeepsItsRoom(): void
    {
        $text = new Text("one\n\nthree\n", new Point(0, 0), new Font($this->fontPath, 10), Color::black());

        $lines = TextLayout::shapes($text);

        self::assertSame(['one', 'three'], array_map(static fn(Text $line): string => $line->text, $lines));
        self::assertSame([0, 24], array_map(static fn(Text $line): int => $line->baseline->y, $lines));
        self::assertSame([], TextLayout::shapes(new Text("\n", new Point(0, 0), new Font($this->fontPath, 10), Color::black())));
    }

    /** @return iterable<string, array{Angle, array{int, int}}> */
    public static function rotatedSecondBaselines(): iterable
    {
        yield 'upright: below' => [Angle::clockwise(0), [100, 148]];
        yield '90 clockwise: to the left' => [Angle::clockwise(90), [52, 100]];
        yield 'upside down: above' => [Angle::clockwise(180), [100, 52]];
        yield '90 counter-clockwise: to the right' => [Angle::counterClockwise(90), [148, 100]];
    }

    /** @param array{int, int} $expected */
    #[DataProvider('rotatedSecondBaselines')]
    public function testTheNextLineFollowsTheTextsOwnDownwardDirection(Angle $angle, array $expected): void
    {
        $text = new Text("a\nb", new Point(100, 100), new Font($this->fontPath, 40), Color::black(), $angle);

        $second = TextLayout::shapes($text)[1];

        self::assertSame($expected, [$second->baseline->x, $second->baseline->y]);
        self::assertTrue($second->angle->equals($angle));
        self::assertSame($expected, [TextLayout::baseline(new Point(100, 100), $angle, 48.0, 1)->x, TextLayout::baseline(new Point(100, 100), $angle, 48.0, 1)->y]);
    }
}
