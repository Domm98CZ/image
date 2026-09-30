<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Format\Binary;

use Domm98CZ\Image\Exception\CorruptedImageException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BinaryStringTest extends TestCase
{
    public function testReadsIntegersInBothByteOrders(): void
    {
        $input = new BinaryString("\x01\x02\x03\x04\x05\x06\x07\x08");

        self::assertSame(0x01, $input->uint8(0));
        self::assertSame(0x0102, $input->uint16BigEndian(0));
        self::assertSame(0x0201, $input->uint16LittleEndian(0));
        self::assertSame(0x030201, $input->uint24LittleEndian(0));
        self::assertSame(0x01020304, $input->uint32BigEndian(0));
        self::assertSame(0x04030201, $input->uint32LittleEndian(0));
        self::assertSame(0x0102030405060708, $input->uint64BigEndian(0));
        self::assertSame("\x03\x04", $input->slice(2, 2));
        self::assertTrue($input->matchesAt(6, "\x07\x08"));
        self::assertFalse($input->matchesAt(7, "\x08\x09"));
    }

    /** @return iterable<string, array{callable(BinaryString): mixed}> */
    public static function outOfBoundsReads(): iterable
    {
        yield 'uint8 at end' => [static fn(BinaryString $input): int => $input->uint8(4)];
        yield 'negative offset' => [static fn(BinaryString $input): int => $input->uint8(-1)];
        yield 'uint32 overlapping end' => [static fn(BinaryString $input): int => $input->uint32BigEndian(1)];
        yield 'slice beyond end' => [static fn(BinaryString $input): string => $input->slice(2, 3)];
        yield 'negative length' => [static fn(BinaryString $input): string => $input->slice(0, -1)];
        yield 'uint64 on short input' => [static fn(BinaryString $input): int => $input->uint64BigEndian(0)];
    }

    /** @param callable(BinaryString): mixed $read */
    #[DataProvider('outOfBoundsReads')]
    public function testOutOfBoundsReadsThrowCorruptedImage(callable $read): void
    {
        $this->expectException(CorruptedImageException::class);

        $read(new BinaryString("\x00\x01\x02\x03"));
    }

    public function testUint64AbovePhpIntMaxIsRejected(): void
    {
        $this->expectException(CorruptedImageException::class);

        (new BinaryString(str_repeat("\xFF", 8)))->uint64BigEndian(0);
    }
}
