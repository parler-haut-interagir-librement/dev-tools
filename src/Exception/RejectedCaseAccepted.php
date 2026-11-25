<?php

declare(strict_types=1);

namespace Phil\DevTools\Exception;

use InvalidArgumentException;

/**
 * A rejected case of the regex generator that its pattern accepts, or a draw asked for without any rejected case.
 */
final class RejectedCaseAccepted extends InvalidArgumentException implements DevToolsException
{
}
