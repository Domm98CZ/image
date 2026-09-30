<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Optimization\External;

use Domm98CZ\Image\Exception\ExternalToolException;
use Domm98CZ\Image\Exception\InvalidInputException;
use Domm98CZ\Image\Exception\InvalidPathException;
use Domm98CZ\Image\Format\Binary\BinaryString;
use Domm98CZ\Image\Format\EncodedImage;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\HeaderProbe;
use Domm98CZ\Image\Format\Header\ImageHeader;
use Domm98CZ\Image\Operation\ColorAdjustment;
use Domm98CZ\Image\Optimization\OptimizerInterface;

// Lossless GIF re-encode (shared color tables, minimal frame diffs) with gifsicle; any other format passes
// through untouched. Works on both static and animated GIFs since gifsicle handles both natively.
final readonly class GifsicleOptimizer implements OptimizerInterface
{
    private const OUTPUT_CAP_MULTIPLIER = 4;
    private const OUTPUT_CAP_ALLOWANCE = 65_536;

    public function __construct(
        private string $binary = 'gifsicle',
        private int $level = 3,
        private float $timeoutSeconds = 30.0,
        private ProcessRunnerInterface $runner = new ProcOpenProcessRunner(),
    ) {
        if ($binary === '' || str_contains($binary, "\0")) {
            throw InvalidPathException::nullByte();
        }
        ColorAdjustment::assertRange('GifsicleOptimizer', 'level', $level, 1, 3);
        ColorAdjustment::assertRange('GifsicleOptimizer', 'timeoutSeconds', $timeoutSeconds, 0.1, 600);
    }

    /** @return non-empty-list<string> */
    public function command(): array
    {
        return [$this->binary, '--optimize=' . $this->level];
    }

    public function optimize(EncodedImage $image): EncodedImage
    {
        if ($image->format !== FormatName::Gif) {
            return $image;
        }

        $header = $this->headerOf($image->bytes);
        $cap = self::OUTPUT_CAP_MULTIPLIER * $image->byteCount() + self::OUTPUT_CAP_ALLOWANCE;
        $result = $this->runner->run($this->command(), $image->bytes, $this->timeoutSeconds, $cap);
        if ($result->exitCode !== 0) {
            throw ExternalToolException::failed('gifsicle', $result->exitCode, $result->stderr);
        }
        $outputHeader = $this->headerOf($result->stdout);
        if (!$outputHeader->canvas->equals($header->canvas)) {
            throw ExternalToolException::invalidOutput('gifsicle', 'dimensions changed');
        }
        if ($outputHeader->frameCount !== $header->frameCount) {
            throw ExternalToolException::invalidOutput('gifsicle', 'frame count changed');
        }

        return strlen($result->stdout) < $image->byteCount() ? new EncodedImage($result->stdout, FormatName::Gif) : $image;
    }

    // The tool's output is untrusted too: it must parse as a GIF before its size is compared.
    private function headerOf(string $bytes): ImageHeader
    {
        try {
            $probe = (new HeaderProbe())->probe(new BinaryString($bytes));
        } catch (InvalidInputException $exception) {
            throw ExternalToolException::invalidOutput('gifsicle', $exception->getMessage());
        }
        if ($probe->format !== FormatName::Gif) {
            throw ExternalToolException::invalidOutput('gifsicle', 'format changed');
        }

        return $probe;
    }
}
