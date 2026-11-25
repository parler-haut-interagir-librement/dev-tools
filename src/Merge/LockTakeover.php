<?php

declare(strict_types=1);

namespace Phil\DevTools\Merge;

/**
 * A merge lock taken over from a merge that stopped: who held it, and why it was taken.
 */
final readonly class LockTakeover
{
    public function __construct(public LockHolder $previous, public string $reason)
    {
    }

    public function describe(): string
    {
        return \sprintf('Took over the merge lock of %s: %s.', $this->previous->describe(), $this->reason);
    }
}
