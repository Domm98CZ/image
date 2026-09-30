<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Format\Header;

use Domm98CZ\Image\Exception\InvalidInputException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

// Attacker-controlled bytes must only ever produce an InvalidInputException or a header: no warnings, TypeErrors or hangs.
final class HeaderProbeRobustnessTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function samples(): iterable
    {
        yield 'jpeg' => [ImageBytes::jpeg(640, 480)];
        yield 'png' => [ImageBytes::png(64, 64, 3, true)];
        yield 'gif' => [ImageBytes::gif(20, 10, [[20, 10], [5, 5]], transparent: true)];
        yield 'webp lossy' => [ImageBytes::webpLossy(30, 20)];
        yield 'webp lossless' => [ImageBytes::webpLossless(30, 20, true)];
        yield 'webp animated' => [ImageBytes::webpExtended(30, 20, true, [[30, 20], [10, 10]])];
        yield 'avif' => [ImageBytes::avif([[64, 64], [16, 16]], alpha: true)];
    }

    #[DataProvider('samples')]
    public function testEveryTruncationFailsCleanly(string $bytes): void
    {
        for ($length = 0; $length < strlen($bytes); ++$length) {
            $this->assertProbeFailsCleanly(substr($bytes, 0, $length), sprintf('prefix of %d bytes', $length));
        }
    }

    #[DataProvider('samples')]
    public function testRandomByteMutationsFailCleanly(string $bytes): void
    {
        mt_srand(crc32($bytes));
        for ($iteration = 0; $iteration < 2_000; ++$iteration) {
            $mutated = $bytes;
            for ($flips = mt_rand(1, 4); $flips > 0; --$flips) {
                $mutated[mt_rand(0, strlen($mutated) - 1)] = chr(mt_rand(0, 255));
            }
            $this->assertProbeFailsCleanly($mutated, sprintf('mutation %d: %s', $iteration, bin2hex($mutated)));
        }
    }

    private function assertProbeFailsCleanly(string $bytes, string $context): void
    {
        set_error_handler(static function (int $severity, string $message) use ($context): never {
            self::fail(sprintf('PHP error "%s" for %s', $message, $context));
        });
        try {
            (new HeaderProbe())->probe(new BinaryString($bytes));
            $this->addToAssertionCount(1);
        } catch (InvalidInputException) {
            $this->addToAssertionCount(1);
        } catch (Throwable $unexpected) {
            self::fail(sprintf('%s "%s" for %s', $unexpected::class, $unexpected->getMessage(), $context));
        } finally {
            restore_error_handler();
        }
    }
}
