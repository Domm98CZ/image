<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Integration\Driver;

use Domm98CZ\Image\Driver\DriverInterface;
use Domm98CZ\Image\Driver\Imagick\ImagickDriver;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('imagick')]
final class ImagickDrawingContractTest extends DrawingContractTestCase
{
    protected function driver(): DriverInterface
    {
        return new ImagickDriver();
    }
}
