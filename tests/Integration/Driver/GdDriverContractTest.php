<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\Gd\GdDriver;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('gd')]
final class GdDriverContractTest extends DriverSpotCheckContractTestCase
{
    protected function driver(): DriverInterface
    {
        return new GdDriver();
    }
}
