<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Header;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;

final readonly class JpegHeaderProbe implements HeaderProbeInterface
{
    private const START_OF_FRAME_MARKERS = [0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF];
    private const START_OF_SCAN = 0xDA;
    private const END_OF_IMAGE = 0xD9;

    public function probe(BinaryString $input): ImageHeader
    {
        $offset = 2;
        while (true) {
            if ($input->uint8($offset) !== 0xFF) {
                throw CorruptedImageException::invalid(FormatName::Jpeg, sprintf('expected a marker at offset %d', $offset));
            }
            while ($input->uint8($offset + 1) === 0xFF) {
                ++$offset;
            }
            $marker = $input->uint8($offset + 1);
            $offset += 2;

            if (self::isStandalone($marker)) {
                continue;
            }
            if ($marker === self::START_OF_SCAN || $marker === self::END_OF_IMAGE) {
                throw CorruptedImageException::invalid(FormatName::Jpeg, 'no frame header before image data');
            }
            $segmentLength = $input->uint16BigEndian($offset);
            if ($segmentLength < 2) {
                throw CorruptedImageException::invalid(FormatName::Jpeg, sprintf('segment length %d at offset %d', $segmentLength, $offset));
            }
            if (in_array($marker, self::START_OF_FRAME_MARKERS, true)) {
                return new ImageHeader(
                    FormatName::Jpeg,
                    DeclaredDimensions::of(FormatName::Jpeg, $input->uint16BigEndian($offset + 5), $input->uint16BigEndian($offset + 3)),
                );
            }
            $offset += $segmentLength;
        }
    }

    private static function isStandalone(int $marker): bool
    {
        return $marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7);
    }
}
