<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Drawing;

use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\FontCoverage;
use Domm98CZ\Image\Tests\Support\SfntFixtures;
use PHPUnit\Framework\TestCase;

// Synthetic sfnt files with hand-built cmap subtables: what the parser reads, not what any installed font happens to contain.
final class FontCoverageTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testFormat12GroupsGiveExactCoverageAndMissingCodePointsBecomeTheReplacementCharacter(): void
    {
        $coverage = FontCoverage::of($this->font(SfntFixtures::sfnt(['cmap' => SfntFixtures::cmap([[3, 10, SfntFixtures::format12([[0x61, 0x7A, 1], [0x3F, 0x3F, 30], [0xFFFD, 0xFFFD, 31]])]])])));

        self::assertTrue($coverage->isKnown());
        self::assertTrue($coverage->covers(0x61));
        self::assertTrue($coverage->covers(0x7A));
        self::assertFalse($coverage->covers(0x41));
        self::assertFalse($coverage->covers(0x4E38));
        self::assertSame("\u{FFFD}", $coverage->placeholder());
        self::assertSame("a\u{FFFD}b", $coverage->substitute("a\u{4E38}b"));
        self::assertSame('ab', $coverage->substitute('ab'));
    }

    public function testControlAndFormatCharactersAreNeverSubstituted(): void
    {
        $coverage = FontCoverage::of($this->font(SfntFixtures::sfnt(['cmap' => SfntFixtures::cmap([[0, 4, SfntFixtures::format12([[0x61, 0x7A, 1], [0xFFFD, 0xFFFD, 2]])]])])));

        self::assertSame("a\nb\t\u{200B}c\u{200D}", $coverage->substitute("a\nb\t\u{200B}c\u{200D}"));
    }

    public function testFallsBackToAQuestionMarkAndThenLeavesTheTextAloneWhenTheFontLacksThePlaceholders(): void
    {
        $withQuestionMark = FontCoverage::of($this->font(SfntFixtures::sfnt(['cmap' => SfntFixtures::cmap([[3, 1, SfntFixtures::format12([[0x3F, 0x3F, 1], [0x61, 0x62, 2]])]])])));
        $withNeither = FontCoverage::of($this->font(SfntFixtures::sfnt(['cmap' => SfntFixtures::cmap([[3, 1, SfntFixtures::format12([[0x61, 0x62, 1]])]])])));

        self::assertSame('?', $withQuestionMark->placeholder());
        self::assertSame('a?b', $withQuestionMark->substitute("a\u{4E38}b"));
        self::assertNull($withNeither->placeholder());
        self::assertSame("a\u{4E38}b", $withNeither->substitute("a\u{4E38}b"));
    }

    public function testFormat4SegmentsHonourDeltaAndRangeOffsetGlyphLookups(): void
    {
        $font = SfntFixtures::sfnt(['cmap' => SfntFixtures::cmap([[3, 1, SfntFixtures::format4([
            // Plain delta segment: glyph = code + delta, so the code that lands on glyph 0 is unmapped.
            [0x20, 0x2F, 0x10000 - 0x2A, null],
            // Range-offset segment with an explicit glyph 0 for "1".
            [0x30, 0x39, 0, [1, 0, 2, 3, 4, 5, 6, 7, 8, 9]],
            [0x61, 0x7A, 0x10000 - 0x61 + 10, null],
            [0xFFFD, 0xFFFD, 0x10000 - 0xFFFD + 40, null],
        ])]])]);
        $coverage = FontCoverage::of($this->font($font));

        self::assertTrue($coverage->isKnown());
        self::assertTrue($coverage->covers(0x20));
        self::assertFalse($coverage->covers(0x2A));
        self::assertTrue($coverage->covers(0x2B));
        self::assertTrue($coverage->covers(0x30));
        self::assertFalse($coverage->covers(0x31));
        self::assertTrue($coverage->covers(0x39));
        self::assertTrue($coverage->covers(0x61));
        self::assertFalse($coverage->covers(0x5A));
        self::assertSame("0\u{FFFD}2 a\u{FFFD}", $coverage->substitute('012 a*'));
    }

    public function testACollectionIsReadThroughItsFirstFace(): void
    {
        // Table offsets inside a collection are relative to the file, not to the face.
        $face = SfntFixtures::sfnt(['cmap' => SfntFixtures::cmap([[3, 10, SfntFixtures::format12([[0x61, 0x61, 1], [0xFFFD, 0xFFFD, 2]])]])], 16);
        $collection = 'ttcf' . pack('N', 0x00010000) . pack('N', 1) . pack('N', 16) . $face;

        $coverage = FontCoverage::of($this->font($collection));

        self::assertTrue($coverage->isKnown());
        self::assertTrue($coverage->covers(0x61));
        self::assertFalse($coverage->covers(0x62));
    }

    public function testWithoutAReadableUnicodeCmapNothingIsSubstituted(): void
    {
        $noTables = FontCoverage::of($this->font('OTTO' . str_repeat("\0", 60)));
        $macRomanOnly = FontCoverage::of($this->font(SfntFixtures::sfnt(['cmap' => SfntFixtures::cmap([[1, 0, SfntFixtures::format12([[0x61, 0x7A, 1]])]])])));
        $truncated = FontCoverage::of($this->font(substr(SfntFixtures::sfnt(['cmap' => SfntFixtures::cmap([[3, 10, SfntFixtures::format12([[0x61, 0x7A, 1]])]])]), 0, 40)));

        foreach ([$noTables, $macRomanOnly, $truncated] as $coverage) {
            self::assertFalse($coverage->isKnown());
            self::assertTrue($coverage->covers(0x4E38));
            self::assertSame("\u{FFFD}", $coverage->placeholder());
            self::assertSame("a\u{4E38}b", $coverage->substitute("a\u{4E38}b"));
        }
    }

    private function font(string $bytes): Font
    {
        $path = sys_get_temp_dir() . '/coverage-' . bin2hex(random_bytes(4)) . '.ttf';
        file_put_contents($path, $bytes);
        $this->files[] = $path;

        return new Font($path, 12);
    }
}
