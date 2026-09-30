<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Security;

use Domm98CZ\Image\Exception\InputReadException;
use Domm98CZ\Image\Exception\InvalidPathException;
use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Security\InputReader;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Security\LimitType;
use Domm98CZ\Image\Tests\Support\InMemoryStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InputReaderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/image-input-reader-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testFromBytesEnforcesLimit(): void
    {
        $reader = new InputReader(Limits::default()->withMaxInputBytes(4));

        self::assertSame('abcd', $reader->fromBytes('abcd'));
        $this->expectLimitExceeded(5, 4);
        $reader->fromBytes('abcde');
    }

    public function testFromFileReadsWithinLimit(): void
    {
        $path = $this->file('abcd');

        self::assertSame('abcd', (new InputReader(Limits::default()->withMaxInputBytes(4)))->fromFile($path));
    }

    public function testFromFileStopsReadingOneBytePastLimit(): void
    {
        $path = $this->file(str_repeat('x', 1000));

        $this->expectLimitExceeded(5, 4);
        (new InputReader(Limits::default()->withMaxInputBytes(4)))->fromFile($path);
    }

    /** @return iterable<string, array{string}> */
    public static function streamWrapperPaths(): iterable
    {
        yield 'phar' => ['phar:///tmp/archive.phar/image.png'];
        yield 'http' => ['http://example.com/image.png'];
        yield 'data' => ['data://text/plain;base64,SGVsbG8='];
        yield 'php' => ['php://filter/resource=/etc/passwd'];
        yield 'mixed case' => ['FiLe:///etc/passwd'];
    }

    #[DataProvider('streamWrapperPaths')]
    public function testFromFileRejectsStreamWrappers(string $path): void
    {
        $this->expectException(InvalidPathException::class);

        (new InputReader(Limits::default()))->fromFile($path);
    }

    public function testFromFileRejectsNullByte(): void
    {
        $this->expectExceptionObject(InvalidPathException::nullByte());

        (new InputReader(Limits::default()))->fromFile($this->directory . "/image.png\0.txt");
    }

    public function testFromFileRejectsMissingFileAndDirectory(): void
    {
        $reader = new InputReader(Limits::default());

        foreach ([$this->directory . '/missing.png', $this->directory] as $path) {
            try {
                $reader->fromFile($path);
                self::fail('Expected InputReadException for ' . $path);
            } catch (InputReadException $exception) {
                self::assertStringContainsString($path, $exception->getMessage());
            }
        }
    }

    public function testFromStreamRewindsSeekableStream(): void
    {
        $stream = InMemoryStream::positionedAtEnd(str_repeat('a', 200_000));

        self::assertSame(200_000, strlen((new InputReader(Limits::default()))->fromStream($stream)));
    }

    public function testFromStreamReadsFromCurrentPositionWhenNotSeekable(): void
    {
        self::assertSame('abc', (new InputReader(Limits::default()))->fromStream(new InMemoryStream('abc', seekable: false)));
    }

    public function testFromStreamEnforcesLimitOnBytesReadNotReportedSize(): void
    {
        $stream = new InMemoryStream(str_repeat('a', 100_000), reportedSize: 10);

        $this->expectLimitExceeded(65_536, 1_000);
        (new InputReader(Limits::default()->withMaxInputBytes(1_000)))->fromStream($stream);
    }

    public function testFromStreamRejectsUnreadableStream(): void
    {
        $this->expectExceptionObject(InputReadException::streamNotReadable());

        (new InputReader(Limits::default()))->fromStream(new InMemoryStream('abc', readable: false));
    }

    public function testFromStreamWrapsStreamFailures(): void
    {
        $failure = new RuntimeException('connection reset');
        $stream = new InMemoryStream('abc', readOverride: static fn(int $length): never => throw $failure);

        try {
            (new InputReader(Limits::default()))->fromStream($stream);
            self::fail('Expected InputReadException.');
        } catch (InputReadException $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }
    }

    public function testFromStreamStopsOnEmptyReadBeforeEof(): void
    {
        $stream = new InMemoryStream('abc', readOverride: static fn(int $length): string => '');

        self::assertSame('', (new InputReader(Limits::default()))->fromStream($stream));
    }

    private function file(string $contents): string
    {
        $path = $this->directory . '/' . bin2hex(random_bytes(4));
        file_put_contents($path, $contents);

        return $path;
    }

    private function expectLimitExceeded(int $actual, int $allowed): void
    {
        $this->expectException(LimitExceededException::class);
        $this->expectExceptionMessage(sprintf('%s is %d, the configured maximum is %d', LimitType::InputBytes->description(), $actual, $allowed));
    }
}
