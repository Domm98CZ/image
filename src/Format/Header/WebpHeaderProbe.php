<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format\Header;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Geometry\Dimensions;

final readonly class WebpHeaderProbe implements HeaderProbeInterface
{
    private const FIRST_CHUNK = 12;
    private const CHUNK_HEADER = 8;
    private const VP8_START_CODE = "\x9D\x01\x2A";
    private const VP8L_SIGNATURE = 0x2F;
    private const VP8X_ALPHA_FLAG = 0x10;
    private const VP8X_ANIMATION_FLAG = 0x02;

    public function probe(BinaryString $input): ImageHeader
    {
        $chunk = $input->slice(self::FIRST_CHUNK, 4);
        $payload = self::FIRST_CHUNK + self::CHUNK_HEADER;

        return match ($chunk) {
            'VP8 ' => $this->lossy($input, $payload),
            'VP8L' => $this->lossless($input, $payload),
            'VP8X' => $this->extended($input, $payload),
            default => throw CorruptedImageException::invalid(FormatName::Webp, sprintf('unknown first chunk "%s"', addcslashes($chunk, "\0..\37\177..\377"))),
        };
    }

    private function lossy(BinaryString $input, int $payload): ImageHeader
    {
        if (!$input->matchesAt($payload + 3, self::VP8_START_CODE)) {
            throw CorruptedImageException::invalid(FormatName::Webp, 'missing VP8 start code');
        }

        return new ImageHeader(FormatName::Webp, DeclaredDimensions::of(
            FormatName::Webp,
            $input->uint16LittleEndian($payload + 6) & 0x3FFF,
            $input->uint16LittleEndian($payload + 8) & 0x3FFF,
        ));
    }

    private function lossless(BinaryString $input, int $payload): ImageHeader
    {
        if ($input->uint8($payload) !== self::VP8L_SIGNATURE) {
            throw CorruptedImageException::invalid(FormatName::Webp, 'missing VP8L signature');
        }
        $bits = $input->uint32LittleEndian($payload + 1);
        if (($bits >> 29) !== 0) {
            throw CorruptedImageException::invalid(FormatName::Webp, 'unknown VP8L version');
        }

        return new ImageHeader(
            FormatName::Webp,
            DeclaredDimensions::of(FormatName::Webp, ($bits & 0x3FFF) + 1, (($bits >> 14) & 0x3FFF) + 1),
            hasAlpha: (($bits >> 28) & 1) === 1,
        );
    }

    private function extended(BinaryString $input, int $payload): ImageHeader
    {
        $flags = $input->uint8($payload);
        $canvas = DeclaredDimensions::of(
            FormatName::Webp,
            $input->uint24LittleEndian($payload + 4) + 1,
            $input->uint24LittleEndian($payload + 7) + 1,
        );
        $hasAlpha = ($flags & self::VP8X_ALPHA_FLAG) !== 0;
        if (($flags & self::VP8X_ANIMATION_FLAG) === 0) {
            return new ImageHeader(FormatName::Webp, $canvas, hasAlpha: $hasAlpha);
        }

        $frameCount = 0;
        $maxWidth = 0;
        $maxHeight = 0;
        $end = min($input->length(), $input->uint32LittleEndian(4) + 8);
        for ($offset = self::FIRST_CHUNK; $offset + self::CHUNK_HEADER <= $end; $offset += self::CHUNK_HEADER + $size + ($size & 1)) {
            $size = $input->uint32LittleEndian($offset + 4);
            if ($input->matchesAt($offset, 'ANMF')) {
                $maxWidth = max($maxWidth, $input->uint24LittleEndian($offset + self::CHUNK_HEADER + 6) + 1);
                $maxHeight = max($maxHeight, $input->uint24LittleEndian($offset + self::CHUNK_HEADER + 9) + 1);
                ++$frameCount;
            }
        }
        if ($frameCount === 0) {
            throw CorruptedImageException::invalid(FormatName::Webp, 'animation flag set but no frames');
        }

        return new ImageHeader(FormatName::Webp, $canvas, $frameCount, $hasAlpha, new Dimensions($maxWidth, $maxHeight));
    }
}
