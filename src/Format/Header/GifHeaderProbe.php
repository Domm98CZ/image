<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Header;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;

final readonly class GifHeaderProbe implements HeaderProbeInterface
{
    private const HEADER_LENGTH = 13;
    private const EXTENSION_INTRODUCER = 0x21;
    private const IMAGE_SEPARATOR = 0x2C;
    private const TRAILER = 0x3B;
    private const GRAPHIC_CONTROL_LABEL = 0xF9;
    private const COLOR_TABLE_FLAG = 0x80;
    private const TRANSPARENCY_FLAG = 0x01;

    public function probe(BinaryString $input): ImageHeader
    {
        $screenWidth = $input->uint16LittleEndian(6);
        $screenHeight = $input->uint16LittleEndian(8);
        $offset = self::HEADER_LENGTH + self::colorTableSize($input->uint8(10));

        $frameCount = 0;
        $hasAlpha = false;
        $maxFrameWidth = 0;
        $maxFrameHeight = 0;

        // A missing trailer at a block boundary is tolerated, as every mainstream decoder does.
        while ($offset < $input->length()) {
            $block = $input->uint8($offset);
            if ($block === self::TRAILER) {
                break;
            }
            if ($block === self::EXTENSION_INTRODUCER) {
                if ($input->uint8($offset + 1) === self::GRAPHIC_CONTROL_LABEL
                    && ($input->uint8($offset + 3) & self::TRANSPARENCY_FLAG) !== 0) {
                    $hasAlpha = true;
                }
                $offset = self::skipSubBlocks($input, $offset + 2);
                continue;
            }
            if ($block !== self::IMAGE_SEPARATOR) {
                throw CorruptedImageException::invalid(FormatName::Gif, sprintf('unexpected block 0x%02X at offset %d', $block, $offset));
            }
            $maxFrameWidth = max($maxFrameWidth, $input->uint16LittleEndian($offset + 5));
            $maxFrameHeight = max($maxFrameHeight, $input->uint16LittleEndian($offset + 7));
            $offset += 10 + self::colorTableSize($input->uint8($offset + 9));
            // Skip the LZW minimum code size byte, then the image data sub-blocks.
            $offset = self::skipSubBlocks($input, $offset + 1);
            ++$frameCount;
        }

        if ($frameCount === 0) {
            throw CorruptedImageException::invalid(FormatName::Gif, 'no image data');
        }
        $largestFrame = DeclaredDimensions::of(FormatName::Gif, $maxFrameWidth, $maxFrameHeight);
        // A zero logical screen is legal; decoders then size the canvas from the frames.
        $canvas = $screenWidth > 0 && $screenHeight > 0
            ? DeclaredDimensions::of(FormatName::Gif, $screenWidth, $screenHeight)
            : $largestFrame;

        return new ImageHeader(FormatName::Gif, $canvas, $frameCount, $hasAlpha, $largestFrame);
    }

    private static function colorTableSize(int $packedFields): int
    {
        return ($packedFields & self::COLOR_TABLE_FLAG) !== 0 ? 3 * (2 << ($packedFields & 0x07)) : 0;
    }

    private static function skipSubBlocks(BinaryString $input, int $offset): int
    {
        while (($size = $input->uint8($offset)) !== 0) {
            $offset += 1 + $size;
        }

        return $offset + 1;
    }
}
