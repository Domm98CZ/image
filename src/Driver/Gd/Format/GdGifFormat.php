<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Format;

use Domm98CZ\Image\Color\Color;
use Domm98CZ\Image\Driver\Gd\GdCanvas;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\GifOutput;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Domm98CZ\Image\Format\Output\OutputOptions;
use GdImage;

final readonly class GdGifFormat implements GdFormatInterface
{
    private const PALETTE_COLORS = 256;
    // GIF transparency is binary: GD alpha 64..127 (at least half transparent) becomes the transparent key.
    private const MIN_TRANSPARENT_GD_ALPHA = 64;
    // Muted, unusual colors tried as the transparent key; the first with no image color nearby wins.
    private const KEY_CANDIDATES = [0x5D1F71, 0x136F4B, 0x8C5A0E, 0x2E2E9A, 0x718E2B, 0x9B143C, 0x468388, 0x0E442A];
    // Older libgd quantizes with reduced precision, so a key must keep this distance per channel from real colors.
    private const KEY_CLEARANCE = 8;

    public function format(): FormatName
    {
        return FormatName::Gif;
    }

    public function isSupported(): bool
    {
        return GdCodec::supports(IMG_GIF);
    }

    public function decode(string $bytes): GdImage
    {
        return GdCodec::decode(FormatName::Gif, $bytes);
    }

    // Explicit quantization: imagegif()'s implicit conversion shifts colors on older libgd (Debian bookworm),
    // whose quantizer also ignores alpha and would merge transparent pixels into opaque ones of the same RGB.
    public function encode(GdImage $image, OutputFormatInterface $output): string
    {
        OutputOptions::expect(GifOutput::class, $output);
        $copy = GdCanvas::blank(GdCanvas::dimensions($image), Color::transparent());
        imagecopy($copy, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        $key = self::replaceTransparencyWithKey($copy);

        $palette = GdCanvas::blank(GdCanvas::dimensions($copy), Color::transparent());
        imagecopy($palette, $copy, 0, 0, 0, 0, imagesx($copy), imagesy($copy));
        imagetruecolortopalette($palette, false, self::PALETTE_COLORS);
        imagecolormatch($copy, $palette);
        if ($key !== null) {
            imagecolortransparent($palette, imagecolorclosest($palette, ($key >> 16) & 0xFF, ($key >> 8) & 0xFF, $key & 0xFF));
        }

        return GdCodec::encode(FormatName::Gif, static fn($stream): bool => imagegif($palette, $stream));
    }

    // Returns the RGB key now marking transparent pixels, or null when the image is fully opaque.
    private static function replaceTransparencyWithKey(GdImage $image): ?int
    {
        [$width, $height] = [imagesx($image), imagesy($image)];
        $transparent = [];
        $available = self::KEY_CANDIDATES;
        $seen = [];
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $argb = (int) imagecolorat($image, $x, $y);
                if ((($argb >> 24) & 0x7F) >= self::MIN_TRANSPARENT_GD_ALPHA) {
                    $transparent[] = [$x, $y];
                } elseif (!array_key_exists($argb & 0xFFFFFF, $seen)) {
                    $seen[$argb & 0xFFFFFF] = true;
                    $available = array_filter($available, static fn(int $key): bool => !self::near($key, $argb));
                }
            }
        }
        if ($transparent === []) {
            return null;
        }
        $key = array_values($available)[0] ?? self::KEY_CANDIDATES[0];
        $keyColor = (int) imagecolorallocatealpha($image, ($key >> 16) & 0xFF, ($key >> 8) & 0xFF, $key & 0xFF, 0);
        foreach ($transparent as [$x, $y]) {
            imagesetpixel($image, $x, $y, $keyColor);
        }
        // Remaining (at most half transparent) pixels become opaque: GIF has no partial alpha.
        imagefilter($image, IMG_FILTER_COLORIZE, 0, 0, 0, -127);

        return $key;
    }

    private static function near(int $key, int $argb): bool
    {
        foreach ([16, 8, 0] as $shift) {
            if (abs((($key >> $shift) & 0xFF) - (($argb >> $shift) & 0xFF)) > self::KEY_CLEARANCE) {
                return false;
            }
        }

        return true;
    }
}
