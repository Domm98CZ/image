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
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Operation\ColorAdjustment;
use Domm98CZ\Image\Optimization\OptimizerInterface;

// Lossy PNG palette quantization with pngquant; any other format passes through untouched.
final readonly class PngquantOptimizer implements OptimizerInterface
{
    // pngquant exit codes meaning "result not worth it", not failure.
    private const QUALITY_TOO_LOW = 99;
    private const NOT_SMALLER = 98;
    // Room for the palette, transparency table and chunk framing on top of the pixel data.
    private const HEADER_ALLOWANCE = 65_536;

    public function __construct(
        private string $binary = 'pngquant',
        private int $minQuality = 65,
        private int $maxQuality = 90,
        private int $speed = 4,
        private float $timeoutSeconds = 30.0,
        private ProcessRunnerInterface $runner = new ProcOpenProcessRunner(),
    ) {
        if ($binary === '' || str_contains($binary, "\0")) {
            throw InvalidPathException::nullByte();
        }
        ColorAdjustment::assertRange('PngquantOptimizer', 'minQuality', $minQuality, 0, 100);
        ColorAdjustment::assertRange('PngquantOptimizer', 'maxQuality', $maxQuality, $minQuality, 100);
        ColorAdjustment::assertRange('PngquantOptimizer', 'speed', $speed, 1, 11);
        ColorAdjustment::assertRange('PngquantOptimizer', 'timeoutSeconds', $timeoutSeconds, 0.1, 600);
    }

    /** @return non-empty-list<string> */
    public function command(): array
    {
        return [
            $this->binary,
            sprintf('--quality=%d-%d', $this->minQuality, $this->maxQuality),
            '--speed=' . $this->speed,
            '--strip',
            '--skip-if-larger',
            '-',
        ];
    }

    public function optimize(EncodedImage $image): EncodedImage
    {
        if ($image->format !== FormatName::Png) {
            return $image;
        }

        $canvas = $this->canvasOf($image->bytes);
        $result = $this->runner->run($this->command(), $image->bytes, $this->timeoutSeconds, self::outputCap($canvas));
        if ($result->exitCode === self::QUALITY_TOO_LOW || $result->exitCode === self::NOT_SMALLER) {
            return $image;
        }
        if ($result->exitCode !== 0) {
            throw ExternalToolException::failed('pngquant', $result->exitCode, $result->stderr);
        }
        if (!$this->canvasOf($result->stdout)->equals($canvas)) {
            throw ExternalToolException::invalidOutput('pngquant', 'dimensions changed');
        }

        return strlen($result->stdout) < $image->byteCount() ? new EncodedImage($result->stdout, FormatName::Png) : $image;
    }

    // Even an uncompressed 8-bit palette PNG needs one byte per pixel plus a filter byte per row; double it for slack.
    private static function outputCap(Dimensions $canvas): int
    {
        return 2 * ($canvas->width + 1) * $canvas->height + self::HEADER_ALLOWANCE;
    }

    // The tool's output is untrusted too: it must parse as a PNG before its size is compared.
    private function canvasOf(string $bytes): Dimensions
    {
        try {
            $probe = (new HeaderProbe())->probe(new BinaryString($bytes));
        } catch (InvalidInputException $exception) {
            throw ExternalToolException::invalidOutput('pngquant', $exception->getMessage());
        }
        if ($probe->format !== FormatName::Png) {
            throw ExternalToolException::invalidOutput('pngquant', 'format changed');
        }

        return $probe->canvas;
    }
}
