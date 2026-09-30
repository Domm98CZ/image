<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\Gd\GdDriver;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('gd')]
final class GdDrawingContractTest extends DrawingSpotCheckContractTestCase
{
    protected function driver(): DriverInterface
    {
        return new GdDriver();
    }

    // DejaVu Sans has the emoticon, but libgd cannot be handed a code point beyond the BMP, so GD shows the placeholder.
    public function testAnEmojiIsDrawnAsTheReplacementCharacterBecauseLibgdCannotReachItsGlyph(): void
    {
        if (!is_file(self::FONT)) {
            self::markTestSkipped('DejaVu Sans is not installed (Debian package fonts-dejavu-core).');
        }
        $font = new Font(self::FONT, 40);

        $emoji = $this->factory->text("a\u{1F600}b", $font, Color::black());
        $replacement = $this->factory->text("a\u{FFFD}b", $font, Color::black());
        $latin1 = $this->factory->text("a\u{F0}\u{9F}\u{98}\u{80}b", $font, Color::black());

        self::assertSame($replacement->pixels()->bytes, $emoji->pixels()->bytes, 'measured and rendered as U+FFFD');
        self::assertNotSame($latin1->width(), $emoji->width(), 'no longer the four UTF-8 bytes read as Latin-1');
    }
}
