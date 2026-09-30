<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\Imagick\ImagickDriver;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

// GD is needed too: the shared fixtures are rendered with raw GD.
#[RequiresPhpExtension('gd')]
#[RequiresPhpExtension('imagick')]
final class ImagickDriverContractTest extends DriverContractTestCase
{
    protected function driver(): DriverInterface
    {
        return new ImagickDriver();
    }
}
