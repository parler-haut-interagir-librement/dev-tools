<?php

declare(strict_types=1);

namespace Phil\DevTools\Exception;

use InvalidArgumentException;

/**
 * The `PHIL_CASTOR` settings of a project's `castor.php` name an unknown key, or give a key a value of the wrong type.
 */
final class InvalidCastorSettings extends InvalidArgumentException implements DevToolsException
{
}
