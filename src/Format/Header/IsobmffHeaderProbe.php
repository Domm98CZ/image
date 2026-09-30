<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Header;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;

// Generic ISOBMFF (ftyp/meta/iprp/ipco box) walker: AVIF and HEIC/HEIF share this container structure,
// differing only in ftyp brand (see FormatIdentifier) and the codec inside the coded image items.
final readonly class IsobmffHeaderProbe implements HeaderProbeInterface
{
    // meta is a "full box": 4 bytes of version/flags precede its children.
    private const CONTAINERS = ['meta' => 4, 'iprp' => 0, 'ipco' => 0];
    private const MAX_DEPTH = 4;
    private const MAX_BOXES = 10_000;
    // Version/flags, width, height: 4 bytes each.
    private const ISPE_BODY_LENGTH = 12;
    private const ALPHA_URNS = ['urn:mpeg:mpegB:cicp:systems:auxiliary:alpha', 'urn:mpeg:hevc:2015:auxid:1'];

    public function __construct(
        private FormatName $format,
    ) {}

    public function probe(BinaryString $input): ImageHeader
    {
        $extents = new IsobmffExtents();
        $this->walk($input, 0, $input->length(), 0, $extents);

        if ($extents->maxWidth === 0) {
            throw CorruptedImageException::invalid($this->format, 'no image spatial extents (ispe) box');
        }

        // Grids, thumbnails and alpha planes each carry an ispe; the largest bounds every decode.
        return new ImageHeader(
            $this->format,
            DeclaredDimensions::of($this->format, $extents->maxWidth, $extents->maxHeight),
            hasAlpha: $extents->hasAlpha,
        );
    }

    private function walk(BinaryString $input, int $offset, int $end, int $depth, IsobmffExtents $extents): void
    {
        while ($offset + 8 <= $end) {
            if (++$extents->boxCount > self::MAX_BOXES) {
                throw CorruptedImageException::invalid($this->format, 'too many boxes');
            }
            $size = $input->uint32BigEndian($offset);
            $type = $input->slice($offset + 4, 4);
            $headerLength = 8;
            if ($size === 1) {
                $size = $input->uint64BigEndian($offset + 8);
                $headerLength = 16;
            } elseif ($size === 0) {
                $size = $end - $offset;
            }
            if ($size < $headerLength || $size > $end - $offset) {
                throw CorruptedImageException::invalid($this->format, sprintf('box "%s" at offset %d has invalid size %d', addcslashes($type, "\0..\37\177..\377"), $offset, $size));
            }
            $body = $offset + $headerLength;
            $boxEnd = $offset + $size;

            if (array_key_exists($type, self::CONTAINERS) && $depth < self::MAX_DEPTH) {
                $this->walk($input, $body + self::CONTAINERS[$type], $boxEnd, $depth + 1, $extents);
            } elseif ($type === 'ispe') {
                if ($boxEnd - $body < self::ISPE_BODY_LENGTH) {
                    throw CorruptedImageException::invalid($this->format, sprintf('ispe box at offset %d is too short', $offset));
                }
                $extents->maxWidth = max($extents->maxWidth, $input->uint32BigEndian($body + 4));
                $extents->maxHeight = max($extents->maxHeight, $input->uint32BigEndian($body + 8));
            } elseif ($type === 'auxC') {
                $urn = $input->slice($body + 4, $boxEnd - $body - 4);
                $extents->hasAlpha = $extents->hasAlpha || in_array(strstr($urn, "\0", true), self::ALPHA_URNS, true);
            }
            $offset = $boxEnd;
        }
    }
}
