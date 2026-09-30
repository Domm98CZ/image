<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Header;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Security\LimitType;
use Domm98CZ\Image\Security\LimitViolation;

// Header values are untrusted: they must surface as input errors, never as InvalidDimensionsException.
final class DeclaredDimensions
{
    public static function of(FormatName $format, int $width, int $height): Dimensions
    {
        if ($width < 1 || $height < 1) {
            throw CorruptedImageException::invalid($format, sprintf('declared dimensions %dx%d are not positive', $width, $height));
        }
        if ($width > Dimensions::MAX_SIDE) {
            throw new LimitExceededException(new LimitViolation(LimitType::Width, $width, Dimensions::MAX_SIDE));
        }
        if ($height > Dimensions::MAX_SIDE) {
            throw new LimitExceededException(new LimitViolation(LimitType::Height, $height, Dimensions::MAX_SIDE));
        }

        return new Dimensions($width, $height);
    }
}
