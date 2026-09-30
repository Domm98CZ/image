<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Metadata;

// IPTC-IIM records wrapped in the Photoshop resource JPEG readers expect in APP13.
final readonly class IptcWriter
{
    public const BYLINE = 80;
    public const COPYRIGHT_NOTICE = 116;
    private const UTF8_MARKER = "\x1B%G";

    /** @param array<int, string> $applicationRecords dataset number (record 2) => value */
    public function write(array $applicationRecords): string
    {
        $iim = self::dataset(1, 90, self::UTF8_MARKER);
        foreach ($applicationRecords as $dataset => $value) {
            $iim .= self::dataset(2, $dataset, $value);
        }

        return '8BIM' . pack('n', 0x0404) . "\0\0" . pack('N', strlen($iim)) . $iim . (strlen($iim) % 2 === 1 ? "\0" : '');
    }

    public static function dataset(int $record, int $dataset, string $value): string
    {
        return "\x1C" . chr($record) . chr($dataset) . pack('n', strlen($value)) . $value;
    }
}
