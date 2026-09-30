<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Header;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;

final readonly class PngHeaderProbe implements HeaderProbeInterface
{
    private const SIGNATURE_LENGTH = 8;
    private const CHUNK_OVERHEAD = 12;
    private const IHDR_LENGTH = 13;
    private const MAX_CHUNK_LENGTH = 0x7FFFFFFF;
    private const VALID_COLOR_TYPES = [0, 2, 3, 4, 6];
    private const COLOR_TYPES_WITH_ALPHA = [4, 6];

    public function probe(BinaryString $input): ImageHeader
    {
        $offset = self::SIGNATURE_LENGTH;
        if (!$input->matchesAt($offset + 4, 'IHDR') || $input->uint32BigEndian($offset) !== self::IHDR_LENGTH) {
            throw CorruptedImageException::invalid(FormatName::Png, sprintf('first chunk is not a %d-byte IHDR', self::IHDR_LENGTH));
        }
        $colorType = $input->uint8($offset + 17);
        if (!in_array($colorType, self::VALID_COLOR_TYPES, true)) {
            throw CorruptedImageException::invalid(FormatName::Png, sprintf('unknown color type %d', $colorType));
        }

        return new ImageHeader(
            FormatName::Png,
            DeclaredDimensions::of(FormatName::Png, $input->uint32BigEndian($offset + 8), $input->uint32BigEndian($offset + 12)),
            hasAlpha: in_array($colorType, self::COLOR_TYPES_WITH_ALPHA, true) || $this->hasTransparencyChunk($input, $offset + self::CHUNK_OVERHEAD + self::IHDR_LENGTH),
        );
    }

    private function hasTransparencyChunk(BinaryString $input, int $offset): bool
    {
        while ($input->has($offset, 8)) {
            $length = $input->uint32BigEndian($offset);
            $type = $input->slice($offset + 4, 4);
            if ($type === 'tRNS') {
                return true;
            }
            if ($type === 'IDAT' || $type === 'IEND' || $length > self::MAX_CHUNK_LENGTH) {
                return false;
            }
            $offset += self::CHUNK_OVERHEAD + $length;
        }

        return false;
    }
}
