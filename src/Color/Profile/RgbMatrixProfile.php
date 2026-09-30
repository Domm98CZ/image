<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Color\Profile;

use Domm98CZ\Image\Exception\InvalidColorException;

// Writes a minimal ICC v2 display profile (matrix + tone curves), generated here so no third-party profile file is shipped.
final readonly class RgbMatrixProfile
{
    private const HEADER_LENGTH = 128;
    private const VERSION_2_1 = 0x02100000;
    private const MAX_DESCRIPTION_LENGTH = 200;

    public function __construct(
        public string $description,
        public Xyz $red,
        public Xyz $green,
        public Xyz $blue,
        public ToneCurve $curve,
    ) {
        if ($description === '' || strlen($description) > self::MAX_DESCRIPTION_LENGTH || preg_match('/^[\x20-\x7E]+$/D', $description) !== 1) {
            throw InvalidColorException::profileValueOutOfRange('description length', strlen($description), '1..200 printable ASCII characters');
        }
    }

    // sRGB colorants chromatically adapted to D50 (Bradford), as in the ICC's own sRGB profiles.
    public static function srgb(): self
    {
        return new self('sRGB IEC61966-2.1', ...self::srgbColorants(), curve: ToneCurve::srgb());
    }

    // sRGB primaries with a linear transfer; distinct enough from sRGB to prove that a conversion ran.
    public static function linearSrgbPrimaries(): self
    {
        return new self('Linear Rec. 709', ...self::srgbColorants(), curve: ToneCurve::gamma(1.0));
    }

    public function bytes(): string
    {
        $curve = $this->curve->curveTag();
        $tags = [
            'desc' => self::descriptionTag($this->description),
            'cprt' => 'text' . "\0\0\0\0" . "No copyright, use freely\0",
            'wtpt' => self::xyzTag(Xyz::d50()),
            'rXYZ' => self::xyzTag($this->red),
            'gXYZ' => self::xyzTag($this->green),
            'bXYZ' => self::xyzTag($this->blue),
            'rTRC' => $curve,
            'gTRC' => $curve,
            'bTRC' => $curve,
        ];

        $table = pack('N', count($tags));
        $data = '';
        $offsets = [];
        $dataStart = self::HEADER_LENGTH + 4 + 12 * count($tags);
        foreach ($tags as $signature => $body) {
            // Identical bodies (the three tone curves) are stored once and shared, as ICC allows.
            if (!array_key_exists($body, $offsets)) {
                $offsets[$body] = $dataStart + strlen($data);
                $data .= str_pad($body, (strlen($body) + 3) & ~3, "\0");
            }
            $table .= $signature . pack('NN', $offsets[$body], strlen($body));
        }

        return self::header($dataStart + strlen($data)) . $table . $data;
    }

    /** @return array{red: Xyz, green: Xyz, blue: Xyz} */
    private static function srgbColorants(): array
    {
        return [
            'red' => new Xyz(0.4360747, 0.2225045, 0.0139322),
            'green' => new Xyz(0.3850649, 0.7168786, 0.0971045),
            'blue' => new Xyz(0.1430804, 0.0606169, 0.7141733),
        ];
    }

    private static function header(int $size): string
    {
        return pack('N', $size)
            . "\0\0\0\0"                                   // preferred CMM
            . pack('N', self::VERSION_2_1)
            . 'mntr' . 'RGB ' . 'XYZ '
            . pack('n6', 2026, 1, 1, 0, 0, 0)              // fixed date keeps the bytes reproducible
            . 'acsp'
            . str_repeat("\0", 24)                         // platform, flags, manufacturer, model, attributes
            . pack('N', 0)                                 // perceptual rendering intent
            . self::xyzNumbers(Xyz::d50())
            . str_repeat("\0", 48);                        // creator, profile ID, reserved
    }

    private static function descriptionTag(string $ascii): string
    {
        // textDescriptionType: ASCII part, then empty Unicode and ScriptCode parts.
        return 'desc' . "\0\0\0\0" . pack('N', strlen($ascii) + 1) . $ascii . "\0"
            . pack('NN', 0, 0) . pack('n', 0) . "\0" . str_repeat("\0", 67);
    }

    private static function xyzTag(Xyz $xyz): string
    {
        return 'XYZ ' . "\0\0\0\0" . self::xyzNumbers($xyz);
    }

    private static function xyzNumbers(Xyz $xyz): string
    {
        // s15Fixed16Number, always non-negative here.
        return pack('N3', (int) round($xyz->x * 65536), (int) round($xyz->y * 65536), (int) round($xyz->z * 65536));
    }
}
