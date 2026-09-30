<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Driver\Gd;

use Closure;

/**
 * @internal
 * @template T
 */
final readonly class CapturedCall
{
    /** @param T $result */
    private function __construct(
        public mixed $result,
        public ?string $error,
    ) {}

    // GD reports failures as PHP warnings next to a false return; both are needed for a useful exception.
    /**
     * @template R
     * @param Closure(): R $call
     * @return self<R>
     */
    public static function run(Closure $call): self
    {
        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error ??= $message;

            return true;
        });
        try {
            $result = $call();
        } finally {
            restore_error_handler();
        }

        return new self($result, $error);
    }
}
