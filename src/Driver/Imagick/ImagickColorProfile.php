<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick;

use Domm98CZ\Image\Color\Profile\ColorProfile;
use Domm98CZ\Image\Color\Profile\RgbMatrixProfile;
use Imagick;
use ImagickException;

/** @internal Brings decoded pixels into sRGB, the color space every other step and every encoder assumes. */
final class ImagickColorProfile
{
    private static ?string $srgb = null;

    public static function toSrgb(Imagick $image): void
    {
        $icc = $image->getImageProfiles('icc', true)['icc'] ?? null;
        if (is_string($icc)) {
            $profile = ColorProfile::tryParse($icc);
            if ($profile !== null && $profile->needsConversion()) {
                try {
                    // With a profile already attached, ImageMagick converts the pixels from it to the new one (lcms).
                    $image->profileImage('icc', self::$srgb ??= RgbMatrixProfile::srgb()->bytes());
                } catch (ImagickException) {
                    // A profile lcms rejects is ignored, as GD would: the numbers stay and read as sRGB.
                }
            }
            // The pixels are sRGB now (or treated as such), which untagged images mean by convention.
            $image->removeImageProfile('icc');
        }
        if ($image->getImageColorspace() === Imagick::COLORSPACE_CMYK) {
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        }
    }
}
