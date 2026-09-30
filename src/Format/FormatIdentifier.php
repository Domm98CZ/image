<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format;

use Domm98CZ\Image\Exception\UnrecognizedFormatException;
use Domm98CZ\Image\Format\Binary\BinaryString;

final readonly class FormatIdentifier
{
    private const AVIF_BRANDS = ['avif', 'avis'];
    private const HEIC_BRANDS = ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'hevm', 'hevs', 'mif1', 'msf1'];

    public function identify(BinaryString $input): FormatName
    {
        if ($input->length() === 0) {
            throw UnrecognizedFormatException::emptyInput();
        }

        return match (true) {
            $input->matchesAt(0, "\xFF\xD8\xFF") => FormatName::Jpeg,
            $input->matchesAt(0, "\x89PNG\r\n\x1A\n") => FormatName::Png,
            $input->matchesAt(0, 'GIF87a'), $input->matchesAt(0, 'GIF89a') => FormatName::Gif,
            $input->matchesAt(0, 'RIFF') && $input->matchesAt(8, 'WEBP') => FormatName::Webp,
            $this->hasFtypBrand($input, self::AVIF_BRANDS) => FormatName::Avif,
            $this->hasFtypBrand($input, self::HEIC_BRANDS) => FormatName::Heic,
            default => throw UnrecognizedFormatException::unknownSignature(),
        };
    }

    /** @param list<string> $brands */
    private function hasFtypBrand(BinaryString $input, array $brands): bool
    {
        if (!$input->matchesAt(4, 'ftyp') || !$input->has(0, 16)) {
            return false;
        }
        $boxEnd = min($input->uint32BigEndian(0), $input->length());
        if (in_array($input->slice(8, 4), $brands, true)) {
            return true;
        }
        for ($offset = 16; $offset + 4 <= $boxEnd; $offset += 4) {
            if (in_array($input->slice($offset, 4), $brands, true)) {
                return true;
            }
        }

        return false;
    }
}
