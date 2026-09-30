<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick;

use Closure;
use Domm98CZ\Image\Driver\Support;

/** @internal Facts about the installed ImageMagick build, probed once on first use. */
final class ImagickCapabilities
{
    private ?Support $avifEncoding = null;

    private ?Support $avifDecoding = null;

    private ?Support $animatedWebpDecoding = null;

    private ?Support $animatedWebpEncoding = null;

    private ?Support $colorManagement = null;

    /** @param Closure(): Support $probe */
    public function colorManagement(Closure $probe): Support
    {
        return $this->colorManagement ??= $probe();
    }

    /** @param Closure(): Support $probe */
    public function avifEncoding(Closure $probe): Support
    {
        return $this->avifEncoding ??= $probe();
    }

    /** @param Closure(): Support $probe */
    public function avifDecoding(Closure $probe): Support
    {
        return $this->avifDecoding ??= $probe();
    }

    /** @param Closure(): Support $probe */
    public function animatedWebpDecoding(Closure $probe): Support
    {
        return $this->animatedWebpDecoding ??= $probe();
    }

    /** @param Closure(): Support $probe */
    public function animatedWebpEncoding(Closure $probe): Support
    {
        return $this->animatedWebpEncoding ??= $probe();
    }
}
