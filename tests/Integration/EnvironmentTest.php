<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration;

use Imagick;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// Each extension is checked on its own: the library needs at least one of them, not both.
final class EnvironmentTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function formats(): iterable
    {
        yield 'jpeg' => ['JPEG'];
        yield 'png' => ['PNG'];
        yield 'gif' => ['GIF'];
        yield 'webp' => ['WEBP'];
        yield 'avif' => ['AVIF'];
    }

    public function testAtLeastOneDriverExtensionIsLoaded(): void
    {
        self::assertTrue(extension_loaded('gd') || extension_loaded('imagick'), 'The integration suite needs ext-gd or ext-imagick');
    }

    #[RequiresPhpExtension('gd')]
    #[DataProvider('formats')]
    public function testGdSupportsFormat(string $format): void
    {
        $supported = [
            'JPEG' => IMG_JPG,
            'PNG' => IMG_PNG,
            'GIF' => IMG_GIF,
            'WEBP' => IMG_WEBP,
            'AVIF' => IMG_AVIF,
        ];
        self::assertNotSame(0, imagetypes() & $supported[$format]);
    }

    #[RequiresPhpExtension('gd')]
    public function testGdHasFreeType(): void
    {
        self::assertTrue(function_exists('imagettftext'));
    }

    #[RequiresPhpExtension('imagick')]
    #[DataProvider('formats')]
    public function testImagickSupportsFormat(string $format): void
    {
        self::assertContains($format, Imagick::queryFormats($format));
    }
}
