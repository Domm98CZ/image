<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Output;

use Domm98CZ\Image\Exception\InvalidPathException;
use Domm98CZ\Image\Exception\OutputWriteException;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Output\FileWriter;
use PHPUnit\Framework\TestCase;

final class FileWriterTest extends TestCase
{
    public function testRejectsStreamWrappers(): void
    {
        $this->expectException(InvalidPathException::class);

        (new FileWriter())->write('ftp://example.com/out.png', new EncodedImage('x', FormatName::Png));
    }

    public function testRejectsMissingDirectory(): void
    {
        $this->expectException(OutputWriteException::class);

        (new FileWriter())->write(sys_get_temp_dir() . '/does-not-exist-' . bin2hex(random_bytes(4)) . '/out.png', new EncodedImage('x', FormatName::Png));
    }

    public function testOverwritesExistingFileAndLeavesNoTemporaryFiles(): void
    {
        $directory = sys_get_temp_dir() . '/image-writer-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $path = $directory . '/out.png';
        file_put_contents($path, 'old');

        (new FileWriter())->write($path, new EncodedImage('new', FormatName::Png));

        self::assertSame('new', file_get_contents($path));
        self::assertSame([$path], glob($directory . '/*'));
        unlink($path);
        rmdir($directory);
    }

    public function testRefusesToReplaceADirectory(): void
    {
        $directory = sys_get_temp_dir() . '/image-writer-' . bin2hex(random_bytes(6));
        mkdir($directory . '/out.png', recursive: true);

        try {
            (new FileWriter())->write($directory . '/out.png', new EncodedImage('x', FormatName::Png));
            self::fail('Expected OutputWriteException.');
        } catch (OutputWriteException $exception) {
            self::assertStringContainsString('is a directory', $exception->getMessage());
        } finally {
            rmdir($directory . '/out.png');
            rmdir($directory);
        }
    }

    public function testReplacingAFileKeepsItsPermissions(): void
    {
        $directory = sys_get_temp_dir() . '/image-writer-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $path = $directory . '/out.png';
        file_put_contents($path, 'old');
        chmod($path, 0o640);

        (new FileWriter())->write($path, new EncodedImage('new', FormatName::Png));
        clearstatcache();

        self::assertSame(0o640, fileperms($path) & 0o777);
        self::assertSame([$path], glob($directory . '/*'));
        unlink($path);
        rmdir($directory);
    }
}
