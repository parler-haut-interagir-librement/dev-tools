<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Closure;
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

    /**
     * A package on its own: its `composer.json`, with the PSR-4 directory `src`, and its `phil-dev.php`.
     *
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $composer
     */
    protected function package(array $manifest = [], array $composer = [], ?string $root = null): string
    {
        $root ??= $this->temporaryDirectory();
        $this->put($root.'/composer.json', json_encode([
            'name' => 'phil/example',
            'autoload' => ['psr-4' => ['Phil\\Example\\' => 'src/']],
            ...$composer,
        ], \JSON_THROW_ON_ERROR));
        $this->put($root.'/phil-dev.php', '<?php return '.var_export($manifest, true).';');

        if (!is_dir($root.'/src')) {
            mkdir($root.'/src');
        }

        return $root;
    }

    /**
     * Sets an environment variable for the duration of `$test`, then restores it.
     *
     * @template T
     *
     * @param Closure(): T $test
     *
     * @return T
     */
    protected function withEnvironment(string $name, ?string $value, Closure $test): mixed
    {
        $previous = getenv($name);
        putenv(null === $value ? $name : $name.'='.$value);

        try {
            return $test();
        } finally {
            putenv(false === $previous ? $name : $name.'='.$previous);
        }
    }

    /**
     * A directory holding an executable file for each name, to put on the `PATH`.
     *
     * @param list<string> $names
     */
    protected function executables(array $names): string
    {
        $directory = $this->temporaryDirectory();

        foreach ($names as $name) {
            $this->put($directory.'/'.$name, "#!/bin/sh\nexit 0\n");
            chmod($directory.'/'.$name, 0o755);
        }

        return $directory;
    }
}
