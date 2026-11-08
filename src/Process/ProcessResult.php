<?php

declare(strict_types=1);

namespace Phil\DevTools\Process;

/**
 * What a command returned: its exit code, its standard output when it was captured, and whether it was stopped at the
 * end of its timeout.
 */
final readonly class ProcessResult
{
    public function __construct(public int $exitCode, public string $output = '', public bool $timedOut = false)
    {
    }

    public function successful(): bool
    {
        return 0 === $this->exitCode;
    }
}
