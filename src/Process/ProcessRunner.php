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
     * Runs the command in the directory, its output going to the terminal.
     *
     * @param non-empty-list<string> $command
     * @param array<string, string>  $environment variables added to the inherited environment
     */
    public function run(array $command, string $directory, array $environment = []): ProcessResult;

    /**
     * Runs the command in the directory and captures its standard output, for a command whose output is read.
     *
     * @param non-empty-list<string> $command
     * @param array<string, string>  $environment variables added to the inherited environment
     */
    public function capture(array $command, string $directory, array $environment = []): ProcessResult;
}
