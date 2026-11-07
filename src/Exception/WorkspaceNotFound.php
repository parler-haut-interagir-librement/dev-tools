<?php

declare(strict_types=1);

namespace Phil\DevTools\Exception;

use InvalidArgumentException;

/**
 * A directory that holds no `composer.json` to work on.
 */
final class WorkspaceNotFound extends InvalidArgumentException implements DevToolsException
{
}
