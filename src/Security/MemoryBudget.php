<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Security;

final readonly class MemoryBudget
{
    private function __construct(
        public ?int $availableBytes,
    ) {}

    public static function unlimited(): self
    {
        return new self(null);
    }

    public static function ofBytes(int $availableBytes): self
    {
        return new self(max(0, $availableBytes));
    }

    public static function fromRuntime(): self
    {
        $limit = ini_get('memory_limit');
        if ($limit === '') {
            return self::unlimited();
        }
        $limitBytes = ini_parse_quantity($limit);
        if ($limitBytes < 0) {
            return self::unlimited();
        }

        return self::ofBytes($limitBytes - memory_get_usage(true));
    }

    public function allows(int $bytes): bool
    {
        return $this->availableBytes === null || $bytes <= $this->availableBytes;
    }
}
