<?php

declare(strict_types=1);

namespace Phil\DevTools\Process;

/**
 * What a command returned: its exit code, and its standard output when it was captured.
 */
final readonly class ProcessResult
{
    public function __construct(public int $exitCode, public string $output = '')
    {
    }

    public function successful(): bool
    {
        return 0 === $this->exitCode;
    }
}
