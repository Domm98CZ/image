<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd;

use Domm98CZ\Image\Drawing\Font;
use Domm98CZ\Image\Drawing\FontCoverage;

/**
 * @internal Text as libgd's FreeType binding reads it. libgd decodes UTF-8 sequences of at most three bytes and looks
 * glyphs up through the font's BMP charmap, so a code point beyond the BMP cannot reach its glyph (as four raw bytes it
 * comes out as four Latin-1 glyphs, as a numeric entity as .notdef) and gets the placeholder of a missing glyph instead.
 */
final class GdText
{
    public static function encode(string $text, Font $font): string
    {
        $coverage = FontCoverage::of($font);
        $placeholder = $coverage->placeholder() ?? FontCoverage::REPLACEMENT_CHARACTER;
        // libgd expands HTML entities in plain text (&copy;), so a literal ampersand is spelled as one itself.
        $escaped = str_replace('&', '&amp;', $coverage->substitute($text));

        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', $placeholder, $escaped) ?? $escaped;
    }
}
