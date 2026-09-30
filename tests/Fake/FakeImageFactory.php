<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Tests\Fake;

use Domm98CZ\Image\Configuration;
use Domm98CZ\Image\Driver\DriverRegistry;
use Domm98CZ\Image\ImageFactory;
use Domm98CZ\Image\Output\FileWriter;

final class FakeImageFactory
{
    public static function with(FakeDriver ...$drivers): ImageFactory
    {
        return self::configured(new Configuration(), ...$drivers);
    }

    public static function configured(Configuration $configuration, FakeDriver ...$drivers): ImageFactory
    {
        return new ImageFactory($configuration, new DriverRegistry(array_values($drivers)), new FileWriter());
    }
}
