<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Animation;

use Domm98CZ\Image\Exception\InvalidAnimationException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Geometry\Dimensions;

final readonly class AnimatedImage
{
    // GIF stores repetitions in 16 bits, i.e. at most 65 536 plays.
    public const MAX_PLAY_COUNT = 65_536;

    public Dimensions $dimensions;

    /**
     * @param list<Frame> $frames
     * @param int $playCount how many times the animation plays: 0 = forever, 1 = once
     */
    public function __construct(
        public array $frames,
        public int $playCount = 0,
        public ?FormatName $sourceFormat = null,
    ) {
        if ($frames === []) {
            throw InvalidAnimationException::noFrames();
        }
        $this->dimensions = $frames[0]->image->dimensions;
        foreach ($frames as $index => $frame) {
            if (!$frame->image->dimensions->equals($this->dimensions)) {
                throw InvalidAnimationException::frameSizeMismatch($index, self::size($this->dimensions), self::size($frame->image->dimensions));
            }
        }
        if ($playCount < 0 || $playCount > self::MAX_PLAY_COUNT) {
            throw InvalidAnimationException::outOfRange('play count', $playCount, '0..' . self::MAX_PLAY_COUNT);
        }
    }

    public function frameCount(): int
    {
        return count($this->frames);
    }

    public function durationMilliseconds(): int
    {
        return array_sum(array_map(static fn(Frame $frame): int => $frame->delayMilliseconds, $this->frames));
    }

    /** @param list<Frame> $frames */
    public function withFrames(array $frames): self
    {
        return new self($frames, $this->playCount, $this->sourceFormat);
    }

    public function withPlayCount(int $playCount): self
    {
        return new self($this->frames, $playCount, $this->sourceFormat);
    }

    private static function size(Dimensions $dimensions): string
    {
        return $dimensions->width . 'x' . $dimensions->height;
    }
}
