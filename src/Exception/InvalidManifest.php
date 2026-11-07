<?php

declare(strict_types=1);

namespace Phil\DevTools\Exception;

use InvalidArgumentException;

/**
 * A `phil-dev.php` file that does not return what it must.
 */
final class InvalidManifest extends InvalidArgumentException implements DevToolsException
{
}
