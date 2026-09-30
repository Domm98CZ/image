<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Driver\Gd;

use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Driver\Gd\GdText;
use Domm98CZ\Image\Tests\Support\SfntFixtures;
use PHPUnit\Framework\TestCase;

// libgd reads UTF-8 only up to three bytes and expands HTML entities, so the GD driver rewrites the text it hands over.
final class GdTextTest extends TestCase
{
    // ASCII, Latin-1 and Latin Extended-A, the euro sign, U+FFFD, Old Italic and the emoticons block.
    private const RANGES = [[0x20, 0x7E], [0xA0, 0x17F], [0x20AC, 0x20AC], [0xFFFD, 0xFFFD], [0x10300, 0x1031E], [0x1F600, 0x1F64F]];

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testTextWithinTheBasicMultilingualPlanePassesThroughUnchanged(): void
    {
        $font = $this->font(SfntFixtures::fontCovering(self::RANGES));

        foreach (["Příliš žluťoučký kůň úpěl ďábelské ódy\n1 299 €", "a\u{FFFD}b", ' ', '%w @/etc/hostname'] as $text) {
            self::assertSame($text, GdText::encode($text, $font));
        }
    }

    public function testAnAmpersandIsSpelledAsItsOwnEntitySoLibgdDrawsItLiterally(): void
    {
        $font = $this->font(SfntFixtures::fontCovering(self::RANGES));

        self::assertSame('AT&amp;T', GdText::encode('AT&T', $font));
        self::assertSame('&amp;copy; &amp;amp; &amp;#38;', GdText::encode('&copy; &amp; &#38;', $font));
        self::assertSame('&amp;', GdText::encode('&', $font));
    }

    public function testACodePointBeyondTheBmpBecomesThePlaceholderEvenWhenTheFontHasItsGlyph(): void
    {
        $font = $this->font(SfntFixtures::fontCovering(self::RANGES));

        self::assertSame("a\u{FFFD}b", GdText::encode("a\u{10300}b", $font));
        self::assertSame("\u{FFFD}", GdText::encode("\u{10000}", $font));
        self::assertSame("a\u{FFFD}\u{FFFD}b", GdText::encode("a\u{1F600}\u{1F601}b", $font));
    }

    public function testThePlaceholderFollowsTheFontLikeAMissingGlyphDoes(): void
    {
        $withQuestionMark = $this->font(SfntFixtures::fontCovering([[0x20, 0x7E], [0x1F600, 0x1F600]]));
        $withNeither = $this->font(SfntFixtures::fontCovering([[0x20, 0x3E], [0x40, 0x7E], [0x1F600, 0x1F600]]));

        self::assertSame('a?b', GdText::encode("a\u{1F600}b", $withQuestionMark));
        // Four Latin-1 glyphs would be worse than whatever FreeType paints for U+FFFD, even without a glyph for it.
        self::assertSame("a\u{FFFD}b", GdText::encode("a\u{1F600}b", $withNeither));
    }

    public function testACodePointTheFontLacksGetsTheSamePlaceholder(): void
    {
        $font = $this->font(SfntFixtures::fontCovering(self::RANGES));

        self::assertSame("\u{FFFD} \u{FFFD} \u{FFFD}", GdText::encode("\u{10330} \u{1F389} \u{4E38}", $font));
    }

    public function testUnknownCoverageStillRewritesWhatLibgdCannotRead(): void
    {
        $font = $this->font('OTTO' . str_repeat("\0", 60));

        self::assertSame("&amp;\u{FFFD}\u{FFFD}\u{4E38}", GdText::encode("&\u{10300}\u{1F600}\u{4E38}", $font));
    }

    private function font(string $bytes): Font
    {
        $path = sys_get_temp_dir() . '/gdtext-' . bin2hex(random_bytes(4)) . '.ttf';
        file_put_contents($path, $bytes);
        $this->files[] = $path;

        return new Font($path, 12);
    }
}
