<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Security;

use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Format\FormatName;
use Domm98CZ\Image\Format\Header\ImageHeader;
use Domm98CZ\Image\Geometry\Dimensions;
use Domm98CZ\Image\Security\InputGuard;
use Domm98CZ\Image\Security\Limits;
use Domm98CZ\Image\Security\LimitType;
use Domm98CZ\Image\Security\LimitViolation;
use Domm98CZ\Image\Security\MemoryBudget;
use Domm98CZ\Image\Tests\Support\ImageBytes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InputGuardTest extends TestCase
{
    public function testAcceptsInputWithinLimitsAndKeepsBytes(): void
    {
        $bytes = ImageBytes::png(100, 50);

        $validated = (new InputGuard(Limits::default()))->inspect($bytes, MemoryBudget::unlimited());

        self::assertSame($bytes, $validated->bytes);
        self::assertEquals(new ImageHeader(FormatName::Png, new Dimensions(100, 50), hasAlpha: true), $validated->header);
    }

    /** @return iterable<string, array{Limits, string, LimitViolation}> */
    public static function violations(): iterable
    {
        $png = ImageBytes::png(200, 100);
        yield 'input bytes' => [Limits::default()->withMaxInputBytes(10), $png, new LimitViolation(LimitType::InputBytes, strlen($png), 10)];
        yield 'width' => [Limits::default()->withMaxWidth(199), $png, new LimitViolation(LimitType::Width, 200, 199)];
        yield 'height' => [Limits::default()->withMaxHeight(99), $png, new LimitViolation(LimitType::Height, 100, 99)];
        yield 'pixels' => [Limits::default()->withMaxPixels(19_999), $png, new LimitViolation(LimitType::Pixels, 20_000, 19_999)];
        yield 'decompression bomb with default limits' => [Limits::default(), ImageBytes::png(100_000, 100_000), new LimitViolation(LimitType::Width, 100_000, 16_384)];
        yield 'oversized gif frame on tiny screen' => [
            Limits::default(),
            ImageBytes::gif(1, 1, [[1, 1], [20_000, 2]]),
            new LimitViolation(LimitType::Width, 20_000, 16_384),
        ];
        yield 'frames' => [Limits::default()->withMaxFrames(2), ImageBytes::gif(4, 4, [[4, 4], [4, 4], [4, 4]]), new LimitViolation(LimitType::Frames, 3, 2)];
        yield 'animation pixels' => [
            Limits::default()->withMaxAnimationPixels(47),
            ImageBytes::gif(4, 4, [[4, 4], [4, 4], [4, 4]]),
            new LimitViolation(LimitType::AnimationPixels, 48, 47),
        ];
    }

    #[DataProvider('violations')]
    public function testRejectsInputExceedingLimit(Limits $limits, string $bytes, LimitViolation $expected): void
    {
        try {
            (new InputGuard($limits))->inspect($bytes, MemoryBudget::unlimited());
            self::fail('Expected LimitExceededException.');
        } catch (LimitExceededException $exception) {
            self::assertEquals($expected, $exception->violation);
        }
    }

    public function testHandoffBytesSkipTheByteCapAndTheMemoryEstimateButKeepThePixelLimits(): void
    {
        $guard = new InputGuard(Limits::default()->withMaxInputBytes(10)->withMaxPixels(19_999));

        self::assertEquals(new Dimensions(100, 50), $guard->inspectHandoff(ImageBytes::png(100, 50))->header->canvas);

        try {
            $guard->inspectHandoff(ImageBytes::png(200, 100));
            self::fail('Expected LimitExceededException.');
        } catch (LimitExceededException $exception) {
            self::assertEquals(new LimitViolation(LimitType::Pixels, 20_000, 19_999), $exception->violation);
        }
    }

    public function testRejectsWhenEstimatedDecodeMemoryExceedsBudget(): void
    {
        $header = new ImageHeader(FormatName::Png, new Dimensions(1000, 1000));
        $estimate = InputGuard::estimateDecodeMemory($header);

        self::assertSame(8_000_000, $estimate);

        try {
            (new InputGuard(Limits::default()))->inspect(ImageBytes::png(1000, 1000), MemoryBudget::ofBytes($estimate - 1));
            self::fail('Expected LimitExceededException.');
        } catch (LimitExceededException $exception) {
            self::assertEquals(new LimitViolation(LimitType::Memory, $estimate, $estimate - 1), $exception->violation);
        }
    }

    public function testMemoryCheckCanBeDisabled(): void
    {
        $validated = (new InputGuard(Limits::default()->withMemoryLimitCheck(false)))
            ->inspect(ImageBytes::png(1000, 1000), MemoryBudget::ofBytes(0));

        self::assertSame(1000, $validated->header->canvas->width);
    }

    public function testMemoryEstimateCountsEveryAnimationFrame(): void
    {
        $header = new ImageHeader(FormatName::Gif, new Dimensions(10, 10), 5);

        self::assertSame(6 * 100 * 4, InputGuard::estimateDecodeMemory($header));
    }
}
