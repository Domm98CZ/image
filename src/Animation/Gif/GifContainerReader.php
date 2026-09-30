<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation\Gif;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\DeclaredDimensions;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Geometry\Point;
use Domm98CZ\Image\Geometry\Rectangle;

// Splits a GIF into its frames without decompressing pixels; bounds-checked like the header probes.
final readonly class GifContainerReader
{
    private const HEADER_LENGTH = 13;
    private const EXTENSION = 0x21;
    private const IMAGE = 0x2C;
    private const TRAILER = 0x3B;
    private const GRAPHIC_CONTROL = 0xF9;
    private const APPLICATION = 0xFF;
    // 8-byte application identifier followed by a 3-byte authentication code.
    private const APPLICATION_BLOCK_LENGTH = 11;
    private const LOOP_APPLICATIONS = ['NETSCAPE2.0', 'ANIMEXTS1.0'];

    public function read(BinaryString $input): GifContainer
    {
        if (!$input->matchesAt(0, 'GIF87a') && !$input->matchesAt(0, 'GIF89a')) {
            throw CorruptedImageException::invalid(FormatName::Gif, 'missing GIF signature');
        }
        $screenWidth = $input->uint16LittleEndian(6);
        $screenHeight = $input->uint16LittleEndian(8);
        $globalTableLength = GifBytes::colorTableLength($input->uint8(10));
        $globalColorTable = $globalTableLength > 0 ? $input->slice(self::HEADER_LENGTH, $globalTableLength) : null;
        $offset = self::HEADER_LENGTH + $globalTableLength;

        $frames = [];
        $loopCount = null;
        $control = null;
        while ($offset < $input->length()) {
            $block = $input->uint8($offset);
            if ($block === self::TRAILER) {
                break;
            }
            if ($block === self::EXTENSION) {
                $label = $input->uint8($offset + 1);
                if ($label === self::GRAPHIC_CONTROL && $input->uint8($offset + 2) >= 4) {
                    $control = [$input->uint8($offset + 3), $input->uint16LittleEndian($offset + 4), $input->uint8($offset + 6)];
                } elseif ($label === self::APPLICATION && $input->uint8($offset + 2) === self::APPLICATION_BLOCK_LENGTH && in_array($input->slice($offset + 3, self::APPLICATION_BLOCK_LENGTH), self::LOOP_APPLICATIONS, true)
                    && $input->uint8($offset + 14) >= 3 && $input->uint8($offset + 15) === 1) {
                    $loopCount = $input->uint16LittleEndian($offset + 16);
                }
                $offset = self::skipSubBlocks($input, $offset + 2);
                continue;
            }
            if ($block !== self::IMAGE) {
                throw CorruptedImageException::invalid(FormatName::Gif, sprintf('unexpected block 0x%02X at offset %d', $block, $offset));
            }

            $packed = $input->uint8($offset + 9);
            $localTableLength = GifBytes::colorTableLength($packed);
            $dataStart = $offset + 10 + $localTableLength;
            $dataEnd = self::skipSubBlocks($input, $dataStart + 1);
            [$controlPacked, $delay, $transparentIndex] = $control ?? [0, 0, 0];
            $frames[] = new GifFrame(
                new Rectangle(
                    new Point($input->uint16LittleEndian($offset + 1), $input->uint16LittleEndian($offset + 3)),
                    DeclaredDimensions::of(FormatName::Gif, $input->uint16LittleEndian($offset + 5), $input->uint16LittleEndian($offset + 7)),
                ),
                ($packed & 0x40) !== 0,
                $localTableLength > 0 ? $input->slice($offset + 10, $localTableLength) : null,
                ($controlPacked & 0x01) !== 0 ? $transparentIndex : null,
                GifDisposal::fromPackedFields($controlPacked),
                $delay,
                $input->uint8($dataStart),
                $input->slice($dataStart + 1, $dataEnd - $dataStart - 1),
            );
            $control = null;
            $offset = $dataEnd;
        }

        if ($frames === []) {
            throw CorruptedImageException::invalid(FormatName::Gif, 'no image data');
        }

        return new GifContainer(self::screen($screenWidth, $screenHeight, $frames), $globalColorTable, $loopCount, $frames);
    }

    /** @param non-empty-list<GifFrame> $frames */
    private static function screen(int $width, int $height, array $frames): Dimensions
    {
        if ($width > 0 && $height > 0) {
            return DeclaredDimensions::of(FormatName::Gif, $width, $height);
        }
        // A 0x0 logical screen is legal: decoders size the canvas to fit the frames.
        $right = max(array_map(static fn(GifFrame $frame): int => $frame->area->rightExclusive(), $frames));
        $bottom = max(array_map(static fn(GifFrame $frame): int => $frame->area->bottomExclusive(), $frames));

        return DeclaredDimensions::of(FormatName::Gif, $right, $bottom);
    }

    private static function skipSubBlocks(BinaryString $input, int $offset): int
    {
        while (($size = $input->uint8($offset)) !== 0) {
            $offset += 1 + $size;
        }

        return $offset + 1;
    }
}
