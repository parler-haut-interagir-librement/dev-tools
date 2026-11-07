<?php

declare(strict_types=1);

namespace Phil\DevTools\Exception;

use InvalidArgumentException;

/**
 * A package name that the skeleton cannot use.
 */
final class InvalidPackageName extends InvalidArgumentException implements DevToolsException
{
}
