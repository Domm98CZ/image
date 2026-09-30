<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Watermark;

use Domm98CZ\Image\Operation\OperationInterface;

// Changes pixels, so it runs inside the pipeline like any other operation.
interface PixelWatermarkInterface extends WatermarkInterface, OperationInterface {}
