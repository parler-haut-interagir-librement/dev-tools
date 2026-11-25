<?php

declare(strict_types=1);

namespace Phil\DevTools\Exception;

use InvalidArgumentException;

/**
 * A regular expression the regex generator cannot draw from: invalid, too long, or built with a construct it cannot honour.
 */
final class UnsupportedPattern extends InvalidArgumentException implements DevToolsException
{
}
