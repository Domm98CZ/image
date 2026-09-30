<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Format;

enum FormatName: string
{
    case Jpeg = 'jpeg';
    case Png = 'png';
    case Gif = 'gif';
    case Webp = 'webp';
    case Avif = 'avif';
    case Heic = 'heic';

    public static function tryFromFileExtension(string $extension): ?self
    {
        return match (strtolower($extension)) {
            'jpg', 'jpeg', 'jpe' => self::Jpeg,
            'png' => self::Png,
            'gif' => self::Gif,
            'webp' => self::Webp,
            'avif' => self::Avif,
            'heic', 'heif' => self::Heic,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Jpeg => 'JPEG',
            self::Png => 'PNG',
            self::Gif => 'GIF',
            self::Webp => 'WebP',
            self::Avif => 'AVIF',
            self::Heic => 'HEIC',
        };
    }

    public function mimeType(): string
    {
        return 'image/' . $this->value;
    }

    public function fileExtension(): string
    {
        return $this === self::Jpeg ? 'jpg' : $this->value;
    }

    public function supportsAlpha(): bool
    {
        return $this !== self::Jpeg;
    }

    public function supportsAnimation(): bool
    {
        return $this === self::Gif || $this === self::Webp;
    }
}
