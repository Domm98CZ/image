<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Format\FormatName;

final class UnsupportedFormatException extends UnsupportedFeatureException
{
    public static function cannotDecode(FormatName $format, DriverName $driver): self
    {
        return new self(sprintf('Driver "%s" cannot decode %s.', $driver->value, $format->label()));
    }

    public static function cannotEncode(FormatName $format, DriverName $driver): self
    {
        return new self(sprintf('Driver "%s" cannot encode %s with the requested options.', $driver->value, $format->label()));
    }

    public static function cannotEncodeAnimation(FormatName $format): self
    {
        return new self(sprintf('Animations can be encoded as GIF or WebP, not %s.', $format->label()));
    }

    public static function noAnimationCodec(FormatName $format): self
    {
        return new self(sprintf('No available codec handles %s animations (animated WebP needs ext-imagick).', $format->label()));
    }

    public static function cannotEmbedMetadata(FormatName $format): self
    {
        return new self(sprintf('Metadata can be embedded into JPEG, PNG and WebP, not %s.', $format->label()));
    }

    public static function noDriverCanDecode(FormatName $format): self
    {
        return new self(sprintf('No available driver can decode %s.', $format->label()));
    }

    public static function noDriverCanEncode(FormatName $format): self
    {
        return new self(sprintf('No available driver can encode %s with the requested options.', $format->label()));
    }
}
