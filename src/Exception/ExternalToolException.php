<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

final class ExternalToolException extends ProcessingException
{
    private const STDERR_EXCERPT_BYTES = 500;

    public static function cannotStart(string $tool, ?string $reason = null): self
    {
        return new self(sprintf('Cannot start external tool "%s"%s', $tool, $reason === null ? '.' : ': ' . $reason));
    }

    public static function timedOut(string $tool, float $seconds): self
    {
        return new self(sprintf('External tool "%s" did not finish within %.1f s and was stopped.', $tool, $seconds));
    }

    public static function outputTooLarge(string $tool, int $limit): self
    {
        return new self(sprintf('External tool "%s" produced more than %d bytes and was stopped.', $tool, $limit));
    }

    public static function failed(string $tool, int $exitCode, string $stderr): self
    {
        return new self(sprintf('External tool "%s" failed with exit code %d: %s', $tool, $exitCode, trim(substr($stderr, 0, self::STDERR_EXCERPT_BYTES))));
    }

    public static function invalidOutput(string $tool, string $reason): self
    {
        return new self(sprintf('External tool "%s" returned an unusable image: %s.', $tool, $reason));
    }
}
