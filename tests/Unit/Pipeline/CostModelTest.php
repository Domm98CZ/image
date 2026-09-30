<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Pipeline;

use Closure;
use Domm98CZ\Image\Driver\Support;
use Domm98CZ\Image\Exception\InvalidArgumentException;
use Domm98CZ\Image\Exception\InvalidConfigurationException;
use Domm98CZ\Image\Pipeline\CostModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CostModelTest extends TestCase
{
    public function testDefaultsMatchDocumentedValues(): void
    {
        $costs = new CostModel();

        self::assertSame(5.0, $costs->nativeNanosPerPixel);
        self::assertSame(150.0, $costs->phpFallbackNanosPerPixel);
        self::assertSame(10_000.0, $costs->degradedNanosPerPixel);
        self::assertSame(150.0, $costs->transferNanosPerPixel);
    }

    public function testAcceptsCalibratedValues(): void
    {
        $costs = new CostModel(14.9, 143.8, 10_000.0, 163.2);

        self::assertSame(14.9, $costs->nativeNanosPerPixel);
        self::assertSame(143.8, $costs->phpFallbackNanosPerPixel);
        self::assertSame(163.2, $costs->transferNanosPerPixel);
    }

    public function testZeroIsAValidCost(): void
    {
        $free = new CostModel(nativeNanosPerPixel: 0.0, phpFallbackNanosPerPixel: 0.0, degradedNanosPerPixel: 1.0, transferNanosPerPixel: 0.0);

        self::assertSame(0.0, $free->step(Support::Native, 1_000));
        self::assertSame(0.0, $free->step(Support::PhpFallback, 1_000));
        self::assertSame(0.0, $free->transfer(1_000));
        self::assertSame(1_000.0, $free->step(Support::Degraded, 1_000));
    }

    public function testPricesAStepAndATransferPerPixel(): void
    {
        $costs = new CostModel(nativeNanosPerPixel: 2.0, phpFallbackNanosPerPixel: 3.0, degradedNanosPerPixel: 5.0, transferNanosPerPixel: 4.0);

        self::assertSame(20.0, $costs->step(Support::Native, 10));
        self::assertSame(30.0, $costs->step(Support::PhpFallback, 10));
        self::assertSame(50.0, $costs->step(Support::Degraded, 10));
        self::assertSame(INF, $costs->step(Support::None, 10));
        self::assertSame(40.0, $costs->transfer(10));
    }

    /** @return iterable<string, array{string, Closure(): CostModel}> */
    public static function negativeCosts(): iterable
    {
        yield 'native' => ['nativeNanosPerPixel', static fn(): CostModel => new CostModel(nativeNanosPerPixel: -1.0)];
        yield 'not a number' => ['nativeNanosPerPixel', static fn(): CostModel => new CostModel(nativeNanosPerPixel: NAN)];
    }

    /** @param Closure(): CostModel $construct */
    #[DataProvider('negativeCosts')]
    public function testRejectsANegativeCost(string $cost, Closure $construct): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(sprintf('Cost "%s" must be a non-negative number', $cost));

        $construct();
    }

    /** @return iterable<string, array{string, Closure(): CostModel}> */
    public static function degradedNotHighest(): iterable
    {
        yield 'equal to native' => ['"nativeNanosPerPixel" is 5', static fn(): CostModel => new CostModel(nativeNanosPerPixel: 5.0, phpFallbackNanosPerPixel: 1.0, degradedNanosPerPixel: 5.0, transferNanosPerPixel: 1.0)];
        yield 'default degraded below a huge transfer' => ['"transferNanosPerPixel" is 20000', static fn(): CostModel => new CostModel(transferNanosPerPixel: 20_000.0)];
    }

    /** @param Closure(): CostModel $construct */
    #[DataProvider('degradedNotHighest')]
    public function testRejectsADegradedCostThatDoesNotExceedEveryOtherCost(string $offendingCost, Closure $construct): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cost "degradedNanosPerPixel" must exceed every other cost, got ');
        $this->expectExceptionMessageMatches('/' . preg_quote($offendingCost, '/') . '\.$/');

        $construct();
    }

    public function testADegradedCostJustAboveTheHighestOtherCostPasses(): void
    {
        $costs = new CostModel(degradedNanosPerPixel: 150.0 + 1e-9);

        self::assertGreaterThan(150.0, $costs->degradedNanosPerPixel);
    }

    public function testTheValidationErrorIsAnInvalidArgumentWithTheFullMessage(): void
    {
        try {
            new CostModel(degradedNanosPerPixel: 150.0);
            self::fail('Expected an InvalidConfigurationException.');
        } catch (InvalidConfigurationException $exception) {
            self::assertInstanceOf(InvalidArgumentException::class, $exception);
            self::assertSame('Cost "degradedNanosPerPixel" must exceed every other cost, got 150 while "phpFallbackNanosPerPixel" is 150.', $exception->getMessage());
        }
    }
}
