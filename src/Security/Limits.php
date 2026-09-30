<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Security;

use Domm98CZ\Image\Exception\InvalidConfigurationException;

final readonly class Limits
{
    public function __construct(
        public int $maxWidth = 16_384,
        public int $maxHeight = 16_384,
        public int $maxPixels = 50_000_000,
        public int $maxFrames = 500,
        public int $maxAnimationPixels = 200_000_000,
        public int $maxInputBytes = 50 * 1024 * 1024,
        public bool $checkMemoryLimit = true,
    ) {
        self::assertPositive(LimitType::Width, $maxWidth);
        self::assertPositive(LimitType::Height, $maxHeight);
        self::assertPositive(LimitType::Pixels, $maxPixels);
        self::assertPositive(LimitType::Frames, $maxFrames);
        self::assertPositive(LimitType::AnimationPixels, $maxAnimationPixels);
        self::assertPositive(LimitType::InputBytes, $maxInputBytes);
    }

    public static function default(): self
    {
        return new self();
    }

    public function withMaxWidth(int $maxWidth): self
    {
        return $this->with(maxWidth: $maxWidth);
    }

    public function withMaxHeight(int $maxHeight): self
    {
        return $this->with(maxHeight: $maxHeight);
    }

    public function withMaxPixels(int $maxPixels): self
    {
        return $this->with(maxPixels: $maxPixels);
    }

    public function withMaxFrames(int $maxFrames): self
    {
        return $this->with(maxFrames: $maxFrames);
    }

    public function withMaxAnimationPixels(int $maxAnimationPixels): self
    {
        return $this->with(maxAnimationPixels: $maxAnimationPixels);
    }

    public function withMaxInputBytes(int $maxInputBytes): self
    {
        return $this->with(maxInputBytes: $maxInputBytes);
    }

    public function withMemoryLimitCheck(bool $enabled): self
    {
        return $this->with(checkMemoryLimit: $enabled);
    }

    private function with(
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        ?int $maxPixels = null,
        ?int $maxFrames = null,
        ?int $maxAnimationPixels = null,
        ?int $maxInputBytes = null,
        ?bool $checkMemoryLimit = null,
    ): self {
        return new self(
            maxWidth: $maxWidth ?? $this->maxWidth,
            maxHeight: $maxHeight ?? $this->maxHeight,
            maxPixels: $maxPixels ?? $this->maxPixels,
            maxFrames: $maxFrames ?? $this->maxFrames,
            maxAnimationPixels: $maxAnimationPixels ?? $this->maxAnimationPixels,
            maxInputBytes: $maxInputBytes ?? $this->maxInputBytes,
            checkMemoryLimit: $checkMemoryLimit ?? $this->checkMemoryLimit,
        );
    }

    private static function assertPositive(LimitType $limit, int $value): void
    {
        if ($value < 1) {
            throw InvalidConfigurationException::limitNotPositive($limit->value, $value);
        }
    }
}
