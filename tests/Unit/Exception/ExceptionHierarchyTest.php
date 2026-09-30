<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Exception;

use Domm98CZ\Image\Exception\ImageException;
use Domm98CZ\Image\Exception\InvalidArgumentException;
use Domm98CZ\Image\Exception\InvalidInputException;
use Domm98CZ\Image\Exception\LimitExceededException;
use Domm98CZ\Image\Security\LimitType;
use Domm98CZ\Image\Security\LimitViolation;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ExceptionHierarchyTest extends TestCase
{
    public function testLimitExceededIsInvalidInputAndCarriesViolation(): void
    {
        $violation = new LimitViolation(LimitType::Pixels, 60_000_000, 50_000_000);
        $exception = new LimitExceededException($violation);

        self::assertInstanceOf(InvalidInputException::class, $exception);
        self::assertInstanceOf(ImageException::class, $exception);
        self::assertSame($violation, $exception->violation);
        self::assertSame('Limit exceeded: total pixel count is 60000000, the configured maximum is 50000000.', $exception->getMessage());
    }

    public function testEveryExceptionIsAnImageExceptionAndLeavesAreFinal(): void
    {
        $files = glob(dirname(__DIR__, 3) . '/src/Exception/*.php');
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            /** @var class-string $class */
            $class = 'Domm98CZ\\Image\\Exception\\' . basename($file, '.php');
            $reflection = new ReflectionClass($class);

            if ($reflection->isInterface()) {
                continue;
            }
            self::assertTrue($reflection->implementsInterface(ImageException::class), $class);
            self::assertTrue($reflection->isAbstract() || $reflection->isFinal(), $class . ' must be abstract (category) or final (leaf)');
        }
    }

    public function testArgumentErrorsExtendSplInvalidArgumentException(): void
    {
        self::assertTrue(is_subclass_of(InvalidArgumentException::class, \InvalidArgumentException::class));
    }
}
