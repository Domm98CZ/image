<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Color\Profile;

use Domm98CZ\Image\Color\Profile\ColorProfile;
use Domm98CZ\Image\Color\Profile\ProfileColorSpace;
use Domm98CZ\Image\Color\Profile\RgbMatrixProfile;
use Domm98CZ\Image\Color\Profile\ToneCurve;
use Domm98CZ\Image\Color\Profile\Xyz;
use Domm98CZ\Image\Exception\InvalidColorException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ColorProfileTest extends TestCase
{
    public function testWrittenSrgbProfileHasAValidIccV2LayoutWithEveryTagInBounds(): void
    {
        $bytes = RgbMatrixProfile::srgb()->bytes();
        $profile = new BinaryString($bytes);

        self::assertSame(strlen($bytes), $profile->uint32BigEndian(0));
        self::assertSame(0x02100000, $profile->uint32BigEndian(8));
        self::assertSame('mntr', $profile->slice(12, 4));
        self::assertSame('RGB ', $profile->slice(16, 4));
        self::assertSame('XYZ ', $profile->slice(20, 4));
        self::assertSame('acsp', $profile->slice(36, 4));
        self::assertSame([0xF6D6, 0x10000, 0xD32D], [$profile->uint32BigEndian(68), $profile->uint32BigEndian(72), $profile->uint32BigEndian(76)]);

        $count = $profile->uint32BigEndian(128);
        $signatures = [];
        for ($entry = 132; $entry < 132 + 12 * $count; $entry += 12) {
            $signatures[] = $profile->slice($entry, 4);
            $offset = $profile->uint32BigEndian($entry + 4);
            self::assertSame(0, $offset % 4, 'tag data is 4-byte aligned');
            self::assertTrue($profile->has($offset, $profile->uint32BigEndian($entry + 8)));
        }
        self::assertSame(['desc', 'cprt', 'wtpt', 'rXYZ', 'gXYZ', 'bXYZ', 'rTRC', 'gTRC', 'bTRC'], $signatures);
        self::assertSame($profile->uint32BigEndian(132 + 12 * 6 + 4), $profile->uint32BigEndian(132 + 12 * 8 + 4), 'the three tone curves share one body');
    }

    public function testToneCurvesAreEncodedAsIccCurveTypes(): void
    {
        self::assertSame('curv' . "\0\0\0\0" . pack('N', 1) . pack('n', 0x0233), ToneCurve::gamma(2.2)->curveTag());

        $srgb = new BinaryString(ToneCurve::srgb()->curveTag());
        self::assertSame(1024, $srgb->uint32BigEndian(8));
        self::assertSame(0, $srgb->uint16BigEndian(12));
        self::assertSame(65535, $srgb->uint16BigEndian(12 + 2 * 1023));
        // Encoded 0.5 is linear ~0.214 in sRGB.
        self::assertEqualsWithDelta(0.214 * 65535, $srgb->uint16BigEndian(12 + 2 * 512), 70);
    }

    public function testWrittenProfilesParseBackAndOnlyNonSrgbOnesNeedConversion(): void
    {
        $srgb = ColorProfile::tryParse(RgbMatrixProfile::srgb()->bytes());
        $linear = ColorProfile::tryParse(RgbMatrixProfile::linearSrgbPrimaries()->bytes());

        self::assertEquals(new ColorProfile(ProfileColorSpace::Rgb, 'sRGB IEC61966-2.1'), $srgb);
        self::assertTrue($srgb?->isSrgb());
        self::assertFalse($srgb?->needsConversion());
        self::assertEquals(new ColorProfile(ProfileColorSpace::Rgb, 'Linear Rec. 709'), $linear);
        self::assertTrue($linear?->needsConversion());
    }

    public function testReadsVersion4MultiLocalizedDescriptions(): void
    {
        $text = mb_convert_encoding('Display P3', 'UTF-16BE', 'UTF-8');
        $mluc = 'mluc' . "\0\0\0\0" . pack('NN', 1, 12) . 'enUS' . pack('NN', strlen($text), 28) . $text;

        $profile = ColorProfile::tryParse(self::profile('RGB ', ['desc' => $mluc]));

        self::assertSame('Display P3', $profile?->description);
        self::assertTrue($profile?->needsConversion());
    }

    public function testCmykAlwaysNeedsConversionAndGrayNever(): void
    {
        self::assertTrue(ColorProfile::tryParse(self::profile('CMYK', []))?->needsConversion());
        self::assertFalse(ColorProfile::tryParse(self::profile('GRAY', []))?->needsConversion());
        self::assertSame(ProfileColorSpace::Other, ColorProfile::tryParse(self::profile('Lab ', []))?->colorSpace);
    }

    /** @return iterable<string, array{string}> */
    public static function malformed(): iterable
    {
        yield 'empty' => [''];
        yield 'no acsp signature' => [str_repeat("\0", 200)];
        yield 'header only' => [substr(self::profile('RGB ', []), 0, 128)];
        yield 'description beyond the end' => [self::profile('RGB ', ['desc' => 'desc' . "\0\0\0\0" . pack('N', 5000) . 'x'])];
    }

    #[DataProvider('malformed')]
    public function testMalformedProfilesAreIgnoredRatherThanFailing(string $bytes): void
    {
        self::assertNull(ColorProfile::tryParse($bytes));
    }

    public function testAHugeTagCountIsNotTrustedAndYieldsNoDescription(): void
    {
        $bytes = substr_replace(self::profile('RGB ', []), pack('N', 0xFFFFFFFF), 128, 4);

        self::assertEquals(new ColorProfile(ProfileColorSpace::Rgb, ''), ColorProfile::tryParse($bytes));
    }

    public function testProfileValuesAreValidated(): void
    {
        $this->expectException(InvalidColorException::class);

        new RgbMatrixProfile("sRGB\n", new Xyz(0.4, 0.2, 0.0), new Xyz(0.4, 0.7, 0.1), new Xyz(0.1, 0.1, 0.7), ToneCurve::gamma(2.2));
    }

    public function testGammaAndXyzRangesAreValidated(): void
    {
        foreach ([static fn(): ToneCurve => ToneCurve::gamma(0.0), static fn(): Xyz => new Xyz(-0.1, 0.0, 0.0), static fn(): Xyz => new Xyz(NAN, 0.0, 0.0)] as $invalid) {
            try {
                $invalid();
                self::fail('Expected InvalidColorException.');
            } catch (InvalidColorException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** @param array<string, string> $tags */
    private static function profile(string $colorSpace, array $tags): string
    {
        $table = pack('N', count($tags));
        $data = '';
        $start = 128 + 4 + 12 * count($tags);
        foreach ($tags as $signature => $body) {
            $table .= $signature . pack('NN', $start + strlen($data), strlen($body));
            $data .= $body;
        }
        $header = pack('N', $start + strlen($data)) . str_repeat("\0", 12) . $colorSpace . 'XYZ ' . str_repeat("\0", 12) . 'acsp' . str_repeat("\0", 88);

        return $header . $table . $data;
    }
}
