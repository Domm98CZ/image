<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Color\Profile;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;

// What the library needs to know about an embedded ICC profile: which colors its numbers mean.
final readonly class ColorProfile
{
    private const HEADER_LENGTH = 128;
    private const TAG_ENTRY_LENGTH = 12;
    private const MAX_DESCRIPTION_BYTES = 1024;

    public function __construct(
        public ProfileColorSpace $colorSpace,
        public string $description,
    ) {}

    // Untrusted bytes: anything malformed is reported as "no usable profile" rather than failing the image.
    public static function tryParse(string $bytes): ?self
    {
        $profile = new BinaryString($bytes);
        try {
            if (!$profile->matchesAt(36, 'acsp') || !$profile->has(self::HEADER_LENGTH, 4)) {
                return null;
            }

            return new self(ProfileColorSpace::fromSignature($profile->slice(16, 4)), self::description($profile));
        } catch (CorruptedImageException) {
            return null;
        }
    }

    public function isSrgb(): bool
    {
        return $this->colorSpace === ProfileColorSpace::Rgb && stripos($this->description, 'sRGB') !== false;
    }

    // Grayscale profiles are left alone: their numbers already read correctly as gray.
    public function needsConversion(): bool
    {
        return ($this->colorSpace === ProfileColorSpace::Rgb && !$this->isSrgb()) || $this->colorSpace === ProfileColorSpace::Cmyk;
    }

    private static function description(BinaryString $profile): string
    {
        $count = $profile->uint32BigEndian(self::HEADER_LENGTH);
        if (!$profile->has(self::HEADER_LENGTH + 4, $count * self::TAG_ENTRY_LENGTH)) {
            return '';
        }
        for ($entry = self::HEADER_LENGTH + 4; $count-- > 0; $entry += self::TAG_ENTRY_LENGTH) {
            if ($profile->matchesAt($entry, 'desc')) {
                return self::text($profile, $profile->uint32BigEndian($entry + 4), $profile->uint32BigEndian($entry + 8));
            }
        }

        return '';
    }

    private static function text(BinaryString $profile, int $offset, int $size): string
    {
        $tag = new BinaryString($profile->slice($offset, $size));
        if ($tag->matchesAt(0, 'desc')) {
            // ICC v2 textDescriptionType: ASCII with its own length.
            return rtrim($tag->slice(12, min($tag->uint32BigEndian(8), self::MAX_DESCRIPTION_BYTES)), "\0");
        }
        if ($tag->matchesAt(0, 'mluc') && $tag->uint32BigEndian(8) > 0) {
            // ICC v4 multiLocalizedUnicodeType: the first record is enough to recognise a profile.
            $text = $tag->slice($tag->uint32BigEndian(24), min($tag->uint32BigEndian(20), self::MAX_DESCRIPTION_BYTES));

            return self::asciiFromUtf16BigEndian($text);
        }

        return '';
    }

    private static function asciiFromUtf16BigEndian(string $text): string
    {
        $ascii = '';
        for ($index = 0; $index + 1 < strlen($text); $index += 2) {
            $ascii .= $text[$index] === "\0" ? $text[$index + 1] : '?';
        }

        return rtrim($ascii, "\0");
    }
}
