<?php

declare(strict_types=1);

namespace Phil\DevTools\Process;

use RuntimeException;

/**
 * Runs a command on this machine, without a shell: each argument is passed as it is. The standard streams the command
 * does not capture are those of `phil-dev`.
 */
final class LocalProcessRunner implements ProcessRunner
{
    /**
     * How often a command with a timeout is looked at.
     */
    private const int POLL_MICROSECONDS = 50_000;

    public function run(array $command, string $directory, array $environment = [], ?int $timeout = null): ProcessResult
    {
        $process = $this->open($command, [], $directory, $environment, $pipes);

        if (null === $timeout) {
            return new ProcessResult(proc_close($process));
        }

        $deadline = hrtime(true) + $timeout * 1_000_000_000;

        while (true) {
            $status = proc_get_status($process);

            if (!$status['running']) {
                proc_close($process);

                return new ProcessResult($status['exitcode']);
            }

            if (hrtime(true) >= $deadline) {
                proc_terminate($process);
                proc_close($process);

                return new ProcessResult(0, timedOut: true);
            }

            usleep(self::POLL_MICROSECONDS);
        }
    }

    public function capture(array $command, string $directory, array $environment = []): ProcessResult
    {
        $process = $this->open($command, [1 => ['pipe', 'w']], $directory, $environment, $pipes);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return new ProcessResult(proc_close($process), false === $output ? '' : $output);
    }

    /**
     * @param non-empty-list<string>    $command
     * @param array<int, list<string>>  $descriptors
     * @param array<string, string>     $environment
     * @param array<int, resource>|null $pipes
     *
     * @param-out array<int, resource> $pipes
     *
     * @return resource
     */
    private function open(array $command, array $descriptors, string $directory, array $environment, ?array &$pipes) // @phpstan-ignore typeCoverage.returnTypeCoverage (a process is a resource, which PHP has no type for)
    {
        // The point of this class: every process of phil-dev goes through here, without a shell.
        $process = proc_open($command, $descriptors, $pipes, $directory, [...getenv(), ...$environment]); // @phpstan-ignore disallowed.function

        if (false === $process) {
            throw new RuntimeException(\sprintf('Cannot run %s.', $command[0]));
        }

        return $process;
    }
}
