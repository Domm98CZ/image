<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd\Format;

use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Output\OutputFormatInterface;
use GdImage;

interface GdFormatInterface
{
    public function format(): FormatName;

    public function isSupported(): bool;

    public function decode(string $bytes): GdImage;

    public function encode(GdImage $image, OutputFormatInterface $output): string;
}
