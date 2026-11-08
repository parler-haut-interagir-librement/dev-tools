<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Support;

use Closure;
use Override;
use Phil\DevTools\Process\ProcessResult;
use Phil\DevTools\Process\ProcessRunner;

/**
 * Records the commands instead of running them, and answers each with the next result given, or success.
 */
final class RecordingRunner implements ProcessRunner
{
    /** @var list<array{command: list<string>, directory: string, environment: array<string, string>, capture: bool, timeout: int|null}> */
    public array $runs = [];

    /**
     * Called with each command run, before its result is given: what the command would have done on disk.
     *
     * @var (Closure(list<string>): void)|null
     */
    public ?Closure $onRun = null;

    /**
     * @param list<ProcessResult> $results
     */
    public function __construct(private array $results = [])
    {
    }

    #[Override]
    public function run(array $command, string $directory, array $environment = [], ?int $timeout = null): ProcessResult
    {
        $this->runs[] = ['command' => $command, 'directory' => $directory, 'environment' => $environment, 'capture' => false, 'timeout' => $timeout];

        if ($this->onRun instanceof Closure) {
            ($this->onRun)($command);
        }

        return array_shift($this->results) ?? new ProcessResult(0);
    }

    #[Override]
    public function capture(array $command, string $directory, array $environment = []): ProcessResult
    {
        $this->runs[] = ['command' => $command, 'directory' => $directory, 'environment' => $environment, 'capture' => true, 'timeout' => null];

        return array_shift($this->results) ?? new ProcessResult(0);
    }

    /**
     * @return list<string> each command joined by spaces
     */
    public function commands(): array
    {
        return array_map(static fn (array $run): string => implode(' ', $run['command']), $this->runs);
    }
}
