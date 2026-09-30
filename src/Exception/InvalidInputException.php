<?php

declare(strict_types=1);

namespace Domm98CZ\Image\Exception;

use RuntimeException;

// Untrusted image input was rejected; in an HTTP context this maps to a client error.
abstract class InvalidInputException extends RuntimeException implements ImageException {}
