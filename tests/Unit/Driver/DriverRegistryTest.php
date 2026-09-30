<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit\Driver;

use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\Exception\DriverNotAvailableException;
use Domm98CZ\Image\Tests\Fake\FakeDriver;
use PHPUnit\Framework\TestCase;

final class DriverRegistryTest extends TestCase
{
    public function testRequiresAtLeastOneDriver(): void
    {
        $this->expectExceptionObject(DriverNotAvailableException::none());

        new DriverRegistry([]);
    }

    public function testForcedDriverRestrictsRegistry(): void
    {
        $gd = new FakeDriver(DriverName::Gd);
        $imagick = new FakeDriver(DriverName::Imagick);

        $registry = (new DriverRegistry([$gd, $imagick]))->restrictedTo(Configuration::default()->withForcedDriver(DriverName::Imagick));

        self::assertSame([$imagick], $registry->drivers);
        self::assertSame([DriverName::Imagick], $registry->names());
    }

    public function testForcedDriverMustBeAvailable(): void
    {
        $this->expectExceptionObject(DriverNotAvailableException::forced(DriverName::Imagick));

        (new DriverRegistry([new FakeDriver(DriverName::Gd)]))->restrictedTo(Configuration::default()->withForcedDriver(DriverName::Imagick));
    }

    public function testLookupByName(): void
    {
        $gd = new FakeDriver(DriverName::Gd);
        $registry = new DriverRegistry([$gd]);

        self::assertSame($gd, $registry->get(DriverName::Gd));
        self::assertNull($registry->get(DriverName::Imagick));
    }
}
