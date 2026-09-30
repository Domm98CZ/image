<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Metadata;

// Raw metadata blocks as stored in files: an XMP packet, an EXIF TIFF structure, IPTC IIM records.
final readonly class MetadataSet
{
    public function __construct(
        public ?string $xmp = null,
        public ?string $exif = null,
        public ?string $iptc = null,
    ) {}
}
