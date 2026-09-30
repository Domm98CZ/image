<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Unit;

use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverName;
use Domm98CZ\Image\Pipeline\CostModel;
use Domm98CZ\Image\Security\Limits;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testDefaultsToDefaultLimitsNoForcedDriverAndTheReferenceCostModel(): void
    {
        $configuration = Configuration::default();

        self::assertEquals(Limits::default(), $configuration->limits);
        self::assertNull($configuration->forcedDriver);
        self::assertEquals(new CostModel(), $configuration->costModel);
    }

    public function testWithers(): void
    {
        $limits = Limits::default()->withMaxPixels(1_000);
        $configuration = Configuration::default()->withLimits($limits)->withForcedDriver(DriverName::Imagick);

        self::assertSame($limits, $configuration->limits);
        self::assertSame(DriverName::Imagick, $configuration->forcedDriver);
        self::assertNull($configuration->withoutForcedDriver()->forcedDriver);
        self::assertSame($limits, $configuration->withoutForcedDriver()->limits);
    }

    public function testWithCostModelReplacesOnlyTheCostModel(): void
    {
        $limits = Limits::default()->withMaxPixels(1_000);
        $costModel = new CostModel(transferNanosPerPixel: 20.0);
        $configuration = Configuration::default()->withLimits($limits)->withForcedDriver(DriverName::Gd)->withCostModel($costModel);

        self::assertSame($costModel, $configuration->costModel);
        self::assertSame($limits, $configuration->limits);
        self::assertSame(DriverName::Gd, $configuration->forcedDriver);
    }

    public function testTheOtherWithersKeepTheCostModel(): void
    {
        $costModel = new CostModel(nativeNanosPerPixel: 1.0);
        $configuration = Configuration::default()->withCostModel($costModel);

        self::assertSame($costModel, $configuration->withLimits(Limits::default()->withMaxWidth(10))->costModel);
        self::assertSame($costModel, $configuration->withForcedDriver(DriverName::Imagick)->costModel);
        self::assertSame($costModel, $configuration->withForcedDriver(DriverName::Imagick)->withoutForcedDriver()->costModel);
    }

    public function testPositionalConstructionStillWorksWithTwoArguments(): void
    {
        $limits = Limits::default()->withMaxHeight(10);
        $configuration = new Configuration($limits, DriverName::Gd);

        self::assertSame($limits, $configuration->limits);
        self::assertSame(DriverName::Gd, $configuration->forcedDriver);
        self::assertEquals(new CostModel(), $configuration->costModel);
    }
}
