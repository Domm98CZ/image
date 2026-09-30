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

// Lossy WebP re-encode with cwebp; any other format passes through untouched. cwebp reads and writes WebP
// directly, so this recompresses an already-encoded WebP rather than converting from another format.
final readonly class CwebpOptimizer implements OptimizerInterface
{
    private const OUTPUT_CAP_MULTIPLIER = 4;
    private const OUTPUT_CAP_ALLOWANCE = 65_536;

    public function __construct(
        private string $binary = 'cwebp',
        private int $quality = 80,
        private float $timeoutSeconds = 30.0,
        private ProcessRunnerInterface $runner = new ProcOpenProcessRunner(),
    ) {
        if ($binary === '' || str_contains($binary, "\0")) {
            throw InvalidPathException::nullByte();
        }
        ColorAdjustment::assertRange('CwebpOptimizer', 'quality', $quality, 0, 100);
        ColorAdjustment::assertRange('CwebpOptimizer', 'timeoutSeconds', $timeoutSeconds, 0.1, 600);
    }

    /** @return non-empty-list<string> */
    public function command(): array
    {
        return [$this->binary, '-q', (string) $this->quality, '-m', '6', '-mt', '-o', '-', '--', '-'];
    }

    public function optimize(EncodedImage $image): EncodedImage
    {
        if ($image->format !== FormatName::Webp) {
            return $image;
        }

        $dimensions = $this->dimensionsOf($image->bytes);
        $cap = self::OUTPUT_CAP_MULTIPLIER * $image->byteCount() + self::OUTPUT_CAP_ALLOWANCE;
        $result = $this->runner->run($this->command(), $image->bytes, $this->timeoutSeconds, $cap);
        if ($result->exitCode !== 0) {
            throw ExternalToolException::failed('cwebp', $result->exitCode, $result->stderr);
        }
        if (!$this->dimensionsOf($result->stdout)->equals($dimensions)) {
            throw ExternalToolException::invalidOutput('cwebp', 'dimensions changed');
        }

        return strlen($result->stdout) < $image->byteCount() ? new EncodedImage($result->stdout, FormatName::Webp) : $image;
    }

    // The tool's output is untrusted too: it must parse as a WebP before its size is compared.
    private function dimensionsOf(string $bytes): Dimensions
    {
        try {
            $probe = (new HeaderProbe())->probe(new BinaryString($bytes));
        } catch (InvalidInputException $exception) {
            throw ExternalToolException::invalidOutput('cwebp', $exception->getMessage());
        }
        if ($probe->format !== FormatName::Webp) {
            throw ExternalToolException::invalidOutput('cwebp', 'format changed');
        }

        return $probe->canvas;
    }
}
