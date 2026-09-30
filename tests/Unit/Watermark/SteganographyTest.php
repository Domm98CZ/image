<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Watermark;

use Domm98CZ\Image\Color\PixelBuffer;
use Domm98CZ\Image\Exception\InvalidWatermarkException;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Watermark\Payload;
use Domm98CZ\Image\Watermark\SecretKey;
use Domm98CZ\Image\Watermark\Steganography\DctReader;
use Domm98CZ\Image\Watermark\Steganography\DctWatermark;
use Domm98CZ\Image\Watermark\Steganography\LsbReader;
use Domm98CZ\Image\Watermark\Steganography\LsbWatermark;
use Domm98CZ\Image\Watermark\WatermarkStatus;
use PHPUnit\Framework\TestCase;

final class SteganographyTest extends TestCase
{
    public function testLsbRoundTripUnkeyedAndKeyed(): void
    {
        $pixels = self::noise(120, 80);
        $key = new SecretKey(str_repeat('k', 32));
        $payload = Payload::text('hello, hidden world');

        $unkeyed = (new LsbReader())->read((new LsbWatermark($payload))->apply($pixels));
        $keyed = (new LsbReader())->read((new LsbWatermark($payload, $key))->apply($pixels), $key);

        self::assertSame(WatermarkStatus::Intact, $unkeyed->status);
        self::assertTrue($payload->equals($unkeyed->payload ?? Payload::text('-')));
        self::assertSame(WatermarkStatus::Authentic, $keyed->status);
        self::assertTrue($keyed->isTrusted());
    }

    public function testKeyedLsbCannotBeFoundWithoutTheRightKey(): void
    {
        $marked = (new LsbWatermark(Payload::text('secret'), new SecretKey(str_repeat('a', 32))))->apply(self::noise(80, 80));

        self::assertSame(WatermarkStatus::Absent, (new LsbReader())->read($marked)->status);
        self::assertSame(WatermarkStatus::Absent, (new LsbReader())->read($marked, new SecretKey(str_repeat('b', 32)))->status);
    }

    public function testLsbDetectsTampering(): void
    {
        $marked = (new LsbWatermark(Payload::text('integrity matters')))->apply(self::noise(80, 80));
        $bytes = $marked->bytes;
        // Header is 7 bytes = 56 bits = 19 pixels; flip the lowest bit of a payload channel after it.
        $bytes[25 * 4] = chr(ord($bytes[25 * 4]) ^ 1);

        self::assertSame(WatermarkStatus::Tampered, (new LsbReader())->read($marked->withBytes($bytes))->status);
    }

    public function testLsbLeavesTransparentPixelsAndAlphaUntouched(): void
    {
        $pixels = self::noise(40, 40);
        $bytes = $pixels->bytes;
        for ($pixel = 0; $pixel < 400; ++$pixel) {
            $bytes[$pixel * 4 + 3] = "\x80";
        }
        $pixels = $pixels->withBytes($bytes);

        $marked = (new LsbWatermark(Payload::text('x')))->apply($pixels);

        self::assertSame(substr($pixels->bytes, 0, 1600), substr($marked->bytes, 0, 1600));
        for ($offset = 3; $offset < strlen($marked->bytes); $offset += 4) {
            self::assertSame($pixels->bytes[$offset], $marked->bytes[$offset]);
        }
        self::assertSame(WatermarkStatus::Intact, (new LsbReader())->read($marked)->status);
    }

    public function testLsbRefusesPayloadsThatDoNotFit(): void
    {
        $this->expectException(InvalidWatermarkException::class);

        (new LsbWatermark(new Payload(str_repeat('x', 100))))->apply(self::noise(10, 10));
    }

    public function testUnmarkedImageReadsAsAbsent(): void
    {
        self::assertSame(WatermarkStatus::Absent, (new LsbReader())->read(self::noise(64, 64))->status);
        self::assertSame(WatermarkStatus::Absent, (new DctReader())->read(self::noise(256, 256))->status);
    }

    public function testDctRoundTripSurvivesNoise(): void
    {
        $key = new SecretKey(str_repeat('d', 32));
        $marked = (new DctWatermark(Payload::text('id-42'), $key))->apply(self::noise(256, 256, smooth: true));

        // Simulate lossy re-encoding: +-6 levels of noise on every channel.
        $noisy = $marked->bytes;
        mt_srand(3);
        for ($offset = 0; $offset < strlen($noisy); ++$offset) {
            if ($offset % 4 !== 3) {
                $noisy[$offset] = chr(max(0, min(255, ord($noisy[$offset]) + mt_rand(-6, 6))));
            }
        }
        $reading = (new DctReader())->read($marked->withBytes($noisy), $key);

        self::assertSame(WatermarkStatus::Authentic, $reading->status);
        self::assertSame('id-42', $reading->payload?->bytes);
    }

    public function testDctNeedsEnoughBlocksAndSmallPayloads(): void
    {
        $rejected = 0;
        foreach ([
            static fn() => (new DctWatermark(Payload::text('x')))->apply(self::noise(64, 64)),
            static fn() => new DctWatermark(new Payload(str_repeat('x', DctWatermark::MAX_PAYLOAD_BYTES + 1))),
        ] as $attempt) {
            try {
                $attempt();
            } catch (InvalidWatermarkException) {
                ++$rejected;
            }
        }

        self::assertSame(2, $rejected);
        self::assertSame(WatermarkStatus::Absent, (new DctReader())->read(self::noise(64, 64))->status);
    }

    public function testSecretKeyNeverLeaks(): void
    {
        $key = new SecretKey('super-secret-key-material');

        self::assertStringNotContainsString('super-secret', print_r($key, true));
        self::assertStringNotContainsString('super-secret', (string) json_encode($key));

        $this->expectException(InvalidWatermarkException::class);
        serialize($key);
    }

    public function testSecretKeyMustBeLongEnough(): void
    {
        $this->expectException(InvalidWatermarkException::class);

        new SecretKey('short');
    }

    private static function noise(int $width, int $height, bool $smooth = false): PixelBuffer
    {
        mt_srand($width * 1000 + $height);
        $bytes = '';
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $base = $smooth ? (int) (128 + 60 * sin($x / 9) * cos($y / 11)) : mt_rand(0, 255);
                $bytes .= chr($base) . chr($smooth ? 255 - $base : mt_rand(0, 255)) . chr($smooth ? intdiv($base, 2) : mt_rand(0, 255)) . "\xFF";
            }
        }

        return new PixelBuffer(new Dimensions($width, $height), $bytes);
    }
}
