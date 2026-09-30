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

// Lossless JPEG re-encode (progressive scan order + optimized Huffman tables) via jpegtran; any other format
// passes through untouched. No pixel data is touched, so there is no quality parameter to tune.
final readonly class JpegtranOptimizer implements OptimizerInterface
{
    // A transcode of an already-entropy-coded format stays within a small multiple of the input, legitimately.
    private const OUTPUT_CAP_MULTIPLIER = 4;
    private const OUTPUT_CAP_ALLOWANCE = 65_536;

    public function __construct(
        private string $binary = 'jpegtran',
        private float $timeoutSeconds = 30.0,
        private ProcessRunnerInterface $runner = new ProcOpenProcessRunner(),
    ) {
        if ($binary === '' || str_contains($binary, "\0")) {
            throw InvalidPathException::nullByte();
        }
        ColorAdjustment::assertRange('JpegtranOptimizer', 'timeoutSeconds', $timeoutSeconds, 0.1, 600);
    }

    /** @return non-empty-list<string> */
    public function command(): array
    {
        return [$this->binary, '-optimize', '-progressive', '-copy', 'none'];
    }

    public function optimize(EncodedImage $image): EncodedImage
    {
        if ($image->format !== FormatName::Jpeg) {
            return $image;
        }

        $dimensions = $this->dimensionsOf($image->bytes);
        $cap = self::OUTPUT_CAP_MULTIPLIER * $image->byteCount() + self::OUTPUT_CAP_ALLOWANCE;
        $result = $this->runner->run($this->command(), $image->bytes, $this->timeoutSeconds, $cap);
        if ($result->exitCode !== 0) {
            throw ExternalToolException::failed('jpegtran', $result->exitCode, $result->stderr);
        }
        if (!$this->dimensionsOf($result->stdout)->equals($dimensions)) {
            throw ExternalToolException::invalidOutput('jpegtran', 'dimensions changed');
        }

        return strlen($result->stdout) < $image->byteCount() ? new EncodedImage($result->stdout, FormatName::Jpeg) : $image;
    }

    // The tool's output is untrusted too: it must parse as a JPEG before its size is compared.
    private function dimensionsOf(string $bytes): Dimensions
    {
        try {
            $probe = (new HeaderProbe())->probe(new BinaryString($bytes));
        } catch (InvalidInputException $exception) {
            throw ExternalToolException::invalidOutput('jpegtran', $exception->getMessage());
        }
        if ($probe->format !== FormatName::Jpeg) {
            throw ExternalToolException::invalidOutput('jpegtran', 'format changed');
        }

        return $probe->canvas;
    }
}
