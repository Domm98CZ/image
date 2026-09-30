<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Header;

use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatIdentifier;
use Domm98CZ\Image\Format\FormatName;

final readonly class HeaderProbe implements HeaderProbeInterface
{
    public function __construct(
        private FormatIdentifier $identifier = new FormatIdentifier(),
    ) {}

    public function probe(BinaryString $input): ImageHeader
    {
        $format = $this->identifier->identify($input);
        $probe = match ($format) {
            FormatName::Jpeg => new JpegHeaderProbe(),
            FormatName::Png => new PngHeaderProbe(),
            FormatName::Gif => new GifHeaderProbe(),
            FormatName::Webp => new WebpHeaderProbe(),
            FormatName::Avif, FormatName::Heic => new IsobmffHeaderProbe($format),
        };

        return $probe->probe($input);
    }
}
