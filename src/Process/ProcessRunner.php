<?php

declare(strict_types=1);

namespace Phil\DevTools\Process;

/**
 * Runs a command. The commands of `phil-dev` run the quality tools, Composer and Docker through it, so that their tests
 * can record what would run instead of running it.
 */
interface ProcessRunner
{
    /**
     * Runs the command in the directory, its output going to the terminal. With a timeout, in seconds, the command is
     * stopped once it has run that long, and the result says so.
     *
     * @param non-empty-list<string> $command
     * @param array<string, string>  $environment variables added to the inherited environment
     * @param positive-int|null      $timeout
     */
    public function run(array $command, string $directory, array $environment = [], ?int $timeout = null): ProcessResult;

    /**
     * Runs the command in the directory and captures its standard output, for a command whose output is read.
     *
     * @param non-empty-list<string> $command
     * @param array<string, string>  $environment variables added to the inherited environment
     */
    public function capture(array $command, string $directory, array $environment = []): ProcessResult;
}
