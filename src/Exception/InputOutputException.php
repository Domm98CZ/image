<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use RuntimeException;

abstract class InputOutputException extends RuntimeException implements ImageException {}
