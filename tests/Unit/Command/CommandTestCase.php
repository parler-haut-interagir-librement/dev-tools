<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Override;
use Phil\DevTools\Console\Application;
use Phil\DevTools\Process\ProcessResult;
use Phil\DevTools\Tests\Unit\Support\RecordingRunner;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

abstract class CommandTestCase extends TestCase
{
    use TemporaryDirectory;

    protected RecordingRunner $runner;

    #[Override]
    protected function setUp(): void
    {
        $this->runner = new RecordingRunner();
    }

    /**
     * @param list<ProcessResult> $results
     */
    protected function tester(string $root, string $command, array $results = []): CommandTester
    {
        $this->runner = new RecordingRunner($results);

        return new CommandTester(new Application($root, $this->runner)->find($command));
    }
}
