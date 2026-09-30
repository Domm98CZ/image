<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Security;

use Domm98CZ\Image\Security\MemoryBudget;
use PHPUnit\Framework\TestCase;

final class MemoryBudgetTest extends TestCase
{
    private string $originalMemoryLimit;

    protected function setUp(): void
    {
        $this->originalMemoryLimit = (string) ini_get('memory_limit');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->originalMemoryLimit);
    }

    public function testFixedBudget(): void
    {
        $budget = MemoryBudget::ofBytes(100);

        self::assertTrue($budget->allows(100));
        self::assertFalse($budget->allows(101));
        self::assertSame(0, MemoryBudget::ofBytes(-5)->availableBytes);
        self::assertTrue(MemoryBudget::unlimited()->allows(PHP_INT_MAX));
    }

    public function testUnlimitedRuntimeMemoryLimit(): void
    {
        ini_set('memory_limit', '-1');

        self::assertNull(MemoryBudget::fromRuntime()->availableBytes);
    }

    public function testRuntimeBudgetIsLimitMinusCurrentUsage(): void
    {
        ini_set('memory_limit', '2G');

        $available = MemoryBudget::fromRuntime()->availableBytes;

        self::assertNotNull($available);
        self::assertLessThan(2 * 1024 ** 3, $available);
        self::assertGreaterThan(2 * 1024 ** 3 - memory_get_usage(true) - 1024 * 1024, $available);
    }
}
