<?php

declare(strict_types=1);

namespace Phil\DevTools\Exception;

use Phil\DevTools\Merge\LockHolder;
use RuntimeException;

/**
 * The merge lock of the repository is held by another merge, still alive and moving.
 */
final class MergeLockHeld extends RuntimeException implements DevToolsException
{
    public function __construct(public readonly LockHolder $holder)
    {
        parent::__construct(\sprintf('Another merge holds the lock: %s.', $holder->describe()));
    }
}
