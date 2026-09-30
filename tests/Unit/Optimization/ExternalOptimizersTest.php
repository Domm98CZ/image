<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Optimization;

use Domm98CZ\Image\Exception\ExternalToolException;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Optimization\External\CwebpOptimizer;
use Domm98CZ\Image\Optimization\External\GifsicleOptimizer;
use Domm98CZ\Image\Optimization\External\JpegtranOptimizer;
use Domm98CZ\Image\Optimization\External\ProcessResult;
use Domm98CZ\Image\Optimization\External\ProcessRunnerInterface;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExternalOptimizersTest extends TestCase
{
    public function testJpegtranLeavesOtherFormatsAloneWithoutRunningAnything(): void
    {
        $runner = self::runner(new ProcessResult(1, '', ''));
        $png = new EncodedImage(ImageBytes::png(2, 2), FormatName::Png);

        self::assertSame($png, (new JpegtranOptimizer(runner: $runner))->optimize($png));
        self::assertSame([], $runner->commands);
    }

    public function testJpegtranKeepsTheOriginalWhenTheResultIsNotSmaller(): void
    {
        $input = new EncodedImage(ImageBytes::jpeg(12, 9), FormatName::Jpeg);
        $runner = self::runner(new ProcessResult(0, ImageBytes::jpeg(12, 9) . str_repeat("\0", 500), ''));

        self::assertSame($input, (new JpegtranOptimizer(runner: $runner))->optimize($input));
    }

    /** @return iterable<string, array{ProcessResult}> */
    public static function jpegtranFailures(): iterable
    {
        yield 'crash' => [new ProcessResult(1, '', 'boom')];
        yield 'not a jpeg' => [new ProcessResult(0, 'garbage', '')];
    }

    #[DataProvider('jpegtranFailures')]
    public function testJpegtranFailuresAndUntrustworthyOutputAreReported(ProcessResult $result): void
    {
        $this->expectException(ExternalToolException::class);

        (new JpegtranOptimizer(runner: self::runner($result)))->optimize(new EncodedImage(ImageBytes::jpeg(12, 9) . str_repeat("\0", 100), FormatName::Jpeg));
    }

    public function testCwebpLeavesOtherFormatsAloneWithoutRunningAnything(): void
    {
        $runner = self::runner(new ProcessResult(1, '', ''));
        $jpeg = new EncodedImage(ImageBytes::jpeg(2, 2), FormatName::Jpeg);

        self::assertSame($jpeg, (new CwebpOptimizer(runner: $runner))->optimize($jpeg));
        self::assertSame([], $runner->commands);
    }

    public function testCwebpKeepsTheOriginalWhenTheResultIsNotSmaller(): void
    {
        $input = new EncodedImage(ImageBytes::webpLossless(12, 9, false), FormatName::Webp);
        $runner = self::runner(new ProcessResult(0, ImageBytes::webpLossless(12, 9, false) . str_repeat("\0", 500), ''));

        self::assertSame($input, (new CwebpOptimizer(runner: $runner))->optimize($input));
    }

    /** @return iterable<string, array{ProcessResult}> */
    public static function cwebpFailures(): iterable
    {
        yield 'crash' => [new ProcessResult(1, '', 'boom')];
        yield 'not a webp' => [new ProcessResult(0, 'garbage', '')];
    }

    #[DataProvider('cwebpFailures')]
    public function testCwebpFailuresAndUntrustworthyOutputAreReported(ProcessResult $result): void
    {
        $this->expectException(ExternalToolException::class);

        (new CwebpOptimizer(runner: self::runner($result)))->optimize(new EncodedImage(ImageBytes::webpLossless(12, 9, false) . str_repeat("\0", 100), FormatName::Webp));
    }

    public function testGifsicleLeavesOtherFormatsAloneWithoutRunningAnything(): void
    {
        $runner = self::runner(new ProcessResult(1, '', ''));
        $jpeg = new EncodedImage(ImageBytes::jpeg(2, 2), FormatName::Jpeg);

        self::assertSame($jpeg, (new GifsicleOptimizer(runner: $runner))->optimize($jpeg));
        self::assertSame([], $runner->commands);
    }

    public function testGifsicleKeepsTheOriginalWhenTheResultIsNotSmaller(): void
    {
        $input = new EncodedImage(ImageBytes::gif(12, 9, [[12, 9]]), FormatName::Gif);
        $runner = self::runner(new ProcessResult(0, ImageBytes::gif(12, 9, [[12, 9]]) . str_repeat("\0", 500), ''));

        self::assertSame($input, (new GifsicleOptimizer(runner: $runner))->optimize($input));
    }

    public function testGifsicleRejectsOutputWithADifferentFrameCount(): void
    {
        $input = new EncodedImage(ImageBytes::gif(4, 4, [[4, 4], [4, 4]]), FormatName::Gif);
        $runner = self::runner(new ProcessResult(0, ImageBytes::gif(4, 4, [[4, 4]]), ''));

        $this->expectException(ExternalToolException::class);

        (new GifsicleOptimizer(runner: $runner))->optimize($input);
    }

    /** @return iterable<string, array{ProcessResult}> */
    public static function gifsicleFailures(): iterable
    {
        yield 'crash' => [new ProcessResult(1, '', 'boom')];
        yield 'not a gif' => [new ProcessResult(0, 'garbage', '')];
    }

    #[DataProvider('gifsicleFailures')]
    public function testGifsicleFailuresAndUntrustworthyOutputAreReported(ProcessResult $result): void
    {
        $this->expectException(ExternalToolException::class);

        (new GifsicleOptimizer(runner: self::runner($result)))->optimize(new EncodedImage(ImageBytes::gif(12, 9, [[12, 9]]) . str_repeat("\0", 100), FormatName::Gif));
    }

    private static function runner(ProcessResult $result): StubProcessRunner
    {
        return new StubProcessRunner($result);
    }
}

final class StubProcessRunner implements ProcessRunnerInterface
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<int> */
    public array $limits = [];

    public function __construct(private readonly ProcessResult $result) {}

    public function run(array $command, string $stdin, float $timeoutSeconds, int $maxOutputBytes): ProcessResult
    {
        $this->commands[] = $command;
        $this->limits[] = $maxOutputBytes;

        return $this->result;
    }
}
