<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Imagick\Format;

use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use Imagick;

interface ImagickFormatInterface
{
    public function format(): FormatName;

    public function isSupported(): bool;

    // Encodes a prepared copy; implementations may mutate $image freely.
    public function encode(Imagick $image, OutputFormatInterface $output): string;
}
