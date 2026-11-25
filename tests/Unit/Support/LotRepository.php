<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Support;

use Closure;
use Phil\DevTools\Process\LocalProcessRunner;
use Phil\DevTools\Process\ProcessResult;
use RuntimeException;

/**
 * A throwaway repository with a lot to merge: its checkout of main, whose composer.json has a gates script, and the
 * branch `b/r1-lot` checked out in a worktree beside it, one commit ahead. Composer is a PHP script that writes each
 * call to a log and fails the calls that hold the text of `PHIL_DEV_FAKE_COMPOSER_FAIL`; git runs without the global and
 * system configurations of the machine.
 */
trait LotRepository
{
    use TemporaryDirectory;

    private string $main = '';

    private string $worktree = '';

    private string $composerLog = '';

    private string $composerScript = '';

    /**
     * @param list<string> $gates the entries of the gates script
     */
    private function lotRepository(array $gates = ['@cs', '@phpstan', '@test', '@infection']): void
    {
        $base = $this->temporaryDirectory();
        $this->main = $base.'/repo';
        $this->worktree = $base.'/repo-wt-r1-lot';
        $this->composerLog = $base.'/composer.log';
        $this->composerScript = $base.'/composer.php';
        $this->put($this->composerScript, <<<'PHP'
            <?php
            $call = implode(' ', array_slice($argv, 1));
            file_put_contents((string) getenv('PHIL_DEV_FAKE_COMPOSER_LOG'), basename((string) getcwd()).': '.$call."\n", FILE_APPEND);
            $fail = (string) getenv('PHIL_DEV_FAKE_COMPOSER_FAIL');
            exit('' !== $fail && str_contains($call, $fail) ? 1 : 0);
            PHP);
        $scripts = ['cs' => 'true', 'phpstan' => 'true', 'test' => 'true', 'infection' => 'true', 'gates' => $gates];
        $this->put($this->main.'/composer.json', json_encode(['name' => 'phil/lot', 'scripts' => $scripts], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT)."\n");
        $this->put($this->main.'/.gitignore', "/var/\n/vendor/\n");
        $this->put($this->main.'/README.md', "One\n");
        $this->repositoryGit($this->main, 'init', '--quiet', '--initial-branch=main');
        $this->repositoryGit($this->main, 'config', 'user.name', 'Test');
        $this->repositoryGit($this->main, 'config', 'user.email', 'test@example.com');
        $this->repositoryGit($this->main, 'config', 'commit.gpgsign', 'false');
        $this->commitIn($this->main, 'Départ');
        $this->repositoryGit($this->main, 'worktree', 'add', '--quiet', $this->worktree, '-b', 'b/r1-lot', 'main');
        $this->put($this->worktree.'/lot.txt', "Lot\n");
        $this->commitIn($this->worktree, 'Ajout du lot');
    }

    private function commitIn(string $directory, string $message): void
    {
        $this->repositoryGit($directory, 'add', '--all');
        $this->repositoryGit($directory, 'commit', '--quiet', '--no-verify', '-m', $message);
    }

    private function repositoryGit(string $directory, string ...$arguments): string
    {
        $result = $this->inRepositoryEnvironment(static fn (): ProcessResult => new LocalProcessRunner()->capture(['/bin/sh', '-c', 'exec "$@" 2>/dev/null', 'sh', 'git', ...array_values($arguments)], $directory));

        if (!$result->successful()) {
            throw new RuntimeException('git '.implode(' ', $arguments).' failed in '.$directory.'.');
        }

        return trim($result->output);
    }

    /**
     * @return list<string> each call of Composer: the directory it ran in, then its arguments
     */
    private function composerCalls(): array
    {
        return is_file($this->composerLog) ? explode("\n", trim((string) file_get_contents($this->composerLog))) : [];
    }

    /**
     * Runs `$test` with git isolated from the machine, Composer faked, and the calls holding `$failing` failed.
     *
     * @template T
     *
     * @param Closure(): T $test
     *
     * @return T
     */
    private function inRepositoryEnvironment(Closure $test, string $failing = ''): mixed
    {
        $variables = [
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_CONFIG_NOSYSTEM' => '1',
            // A directory outside the repositories of the test is outside any repository, even under a checkout.
            'GIT_CEILING_DIRECTORIES' => (string) realpath(sys_get_temp_dir()),
            'COMPOSER_BINARY' => $this->composerScript,
            'PHIL_DEV_FAKE_COMPOSER_LOG' => $this->composerLog,
            'PHIL_DEV_FAKE_COMPOSER_FAIL' => $failing,
            'PHIL_DEV_ROOT' => null,
        ];
        $previous = [];

        foreach ($variables as $name => $value) {
            $previous[$name] = getenv($name);
            putenv(null === $value ? $name : $name.'='.$value);
        }

        try {
            return $test();
        } finally {
            foreach ($previous as $name => $value) {
                putenv(false === $value ? $name : $name.'='.$value);
            }
        }
    }
}
