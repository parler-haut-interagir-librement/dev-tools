<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\InfectionCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InfectionCommand::class)]
final class InfectionCommandTest extends CommandTestCase
{
    public function testTheWholeCode(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [new ProcessResult(3)]);

        self::assertSame(3, $tester->execute(['arguments' => ['--filter=X']]));
        self::assertSame([$root.'/vendor-bin/infection/vendor/bin/infection --configuration='.$root.'/var/phil-dev/infection.json --no-progress --show-mutations --threads=max --filter=X'], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[0]['directory']);
        self::assertSame(['TMPDIR' => realpath($root).'/var/tmp'], $this->runner->runs[0]['environment']);
        self::assertStringContainsString('"minCoveredMsi": 100', (string) file_get_contents($root.'/var/phil-dev/infection.json'));
    }

    public function testTheTimeoutOfAPackage(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/slug/phil-dev.php', "<?php return ['infection' => ['timeout' => 60]];");
        $tester = $this->tester($root, 'infection', [new ProcessResult(0)]);

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('"timeout": 60,', (string) file_get_contents($root.'/var/phil-dev/infection.json'));
    }

    public function testThePhpUnitConfigurationWithoutTheSkippedPlannedPackages(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/phpunit.dist.xml', '<phpunit/>');
        $this->put($root.'/packages/slug/phil-dev.php', "<?php return ['status' => 'planned'];");
        $tester = $this->tester($root, 'infection');

        $this->withEnvironment('PHIL_DEV_SKIP_PLANNED', null, static fn (): int => $tester->execute(['--skip-planned' => true]));

        self::assertStringContainsString('"configDir": "'.$root.'/var/phil-dev/phpunit"', (string) file_get_contents($root.'/var/phil-dev/infection.json'));
        self::assertStringNotContainsString($root.'/packages/slug/src', (string) file_get_contents($root.'/var/phil-dev/infection.json'));
        self::assertFileExists($root.'/var/phil-dev/phpunit/phpunit.xml');
    }

    public function testTheRootPhpUnitConfigurationWhenNothingIsSkipped(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/phpunit.dist.xml', '<phpunit/>');
        $tester = $this->tester($root, 'infection');

        $this->withEnvironment('PHIL_DEV_SKIP_PLANNED', null, static fn (): int => $tester->execute([]));

        self::assertStringContainsString('"configDir": "'.$root.'"', (string) file_get_contents($root.'/var/phil-dev/infection.json'));
        self::assertFileDoesNotExist($root.'/var/phil-dev/phpunit/phpunit.xml');
    }

    public function testThePhpUnitConfigurationWithTheGlobsListedDirectoryByDirectory(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/phpunit.dist.xml', '<phpunit><source><include><directory>packages/*/src</directory></include></source></phpunit>');
        $tester = $this->tester($root, 'infection');

        $this->withEnvironment('PHIL_DEV_SKIP_PLANNED', null, static fn (): int => $tester->execute([]));

        self::assertStringContainsString('"configDir": "'.$root.'/var/phil-dev/phpunit"', (string) file_get_contents($root.'/var/phil-dev/infection.json'));
        self::assertStringContainsString('<include><directory>'.$root.'/packages/slug-bundle/src</directory><directory>'.$root.'/packages/slug/src</directory><directory>'.$root.'/packages/tilttek-dice/src</directory></include></source>', (string) file_get_contents($root.'/var/phil-dev/phpunit/phpunit.xml'));
    }

    public function testTheFilesChangedSinceAGivenRef(): void
    {
        $root = $this->fixture('monorepo');
        $directories = $root.'/src '.$root.'/packages/slug/src '.$root.'/packages/slug-bundle/src '.$root.'/packages/tilttek-dice/src';
        $tester = $this->tester($root, 'infection', [
            new ProcessResult(0, "abc123\n"),
            new ProcessResult(0, "src/A.php\npackages/slug/src/C.php\npackages/slug/README.md\n"),
            new ProcessResult(0, "packages/slug/src/B.php\nsrc/A.php\n"),
            new ProcessResult(0),
        ]);

        self::assertSame(0, $tester->execute(['--changed' => 'release/1.0']));
        self::assertSame("Mutating the 3 source files changed since release/1.0.\n", $tester->getDisplay());
        self::assertSame([
            'git merge-base release/1.0 HEAD',
            'git diff --name-only --diff-filter=AMR abc123 -- '.$directories,
            'git ls-files --others --exclude-standard -- '.$directories,
            $root.'/vendor-bin/infection/vendor/bin/infection --configuration='.$root.'/var/phil-dev/infection.json --no-progress --show-mutations --threads=max --filter=packages/slug/src/B.php,packages/slug/src/C.php,src/A.php',
        ], $this->runner->commands());
        self::assertSame([true, true, true, false], array_column($this->runner->runs, 'capture'));
        self::assertSame([$root, $root, $root, $root], array_column($this->runner->runs, 'directory'));
    }

    public function testAnUntrackedFileAlone(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [new ProcessResult(0, "abc123\n"), new ProcessResult(0), new ProcessResult(0, "src/New.php\n")]);

        self::assertSame(0, $tester->execute(['--changed' => 'main']));
        self::assertStringEndsWith(' --filter=src/New.php', $this->runner->commands()[3]);
    }

    public function testTheChangedAndTheNewFilesAreSplitOnTheirOwnLines(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [new ProcessResult(0, "abc123\n"), new ProcessResult(0, 'src/A.php'), new ProcessResult(0, 'src/B.php')]);

        self::assertSame(0, $tester->execute(['--changed' => 'main']));
        self::assertSame("Mutating the 2 source files changed since main.\n", $tester->getDisplay());
    }

    public function testNothingChanged(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [new ProcessResult(128), new ProcessResult(0, "\n"), new ProcessResult(0, "README.md\n")]);

        self::assertSame(0, $tester->execute(['--changed' => 'nowhere']));
        self::assertSame("No source file changed since nowhere: nothing to mutate.\n", $tester->getDisplay());
        self::assertStringContainsString(' --diff-filter=AMR nowhere -- ', $this->runner->commands()[1]);
        self::assertCount(3, $this->runner->runs);
    }

    public function testTheBaseRefFromTheVariable(): void
    {
        $root = $this->fixture('monorepo');
        putenv('PHIL_DEV_BASE_REF=develop');

        try {
            $tester = $this->tester($root, 'infection', [new ProcessResult(0, "def456\n"), new ProcessResult(0, "src/A.php\n")]);
            $tester->execute(['--changed' => null]);
        } finally {
            putenv('PHIL_DEV_BASE_REF');
        }

        self::assertSame('git merge-base develop HEAD', $this->runner->commands()[0]);
        self::assertStringContainsString(' --diff-filter=AMR def456 -- ', $this->runner->commands()[1]);
        self::assertSame("Mutating the 1 source files changed since develop.\n", $tester->getDisplay());
    }

    public function testTheBaseIsTheMainTheBranchLeftLast(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [
            new ProcessResult(0),
            new ProcessResult(0, "old111\n"),
            new ProcessResult(0),
            new ProcessResult(0, "new222\n"),
            new ProcessResult(0),
            new ProcessResult(0, "src/A.php\n"),
        ]);

        $tester->execute(['--changed' => null]);

        self::assertSame([
            'git rev-parse --verify --quiet origin/main',
            'git merge-base origin/main HEAD',
            'git rev-parse --verify --quiet main',
            'git merge-base main HEAD',
            'git merge-base --is-ancestor old111 new222',
        ], \array_slice($this->runner->commands(), 0, 5));
        self::assertStringContainsString(' --diff-filter=AMR new222 -- ', $this->runner->commands()[5]);
        self::assertSame("Mutating the 1 source files changed since main.\n", $tester->getDisplay());
    }

    public function testTheRemoteMainWhenTheLocalOneIsBehind(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [
            new ProcessResult(0),
            new ProcessResult(0, "new222\n"),
            new ProcessResult(0),
            new ProcessResult(0, "old111\n"),
            new ProcessResult(1),
            new ProcessResult(0, "src/A.php\n"),
        ]);

        $tester->execute(['--changed' => null]);

        self::assertStringContainsString(' --diff-filter=AMR new222 -- ', $this->runner->commands()[5]);
        self::assertSame("Mutating the 1 source files changed since origin/main.\n", $tester->getDisplay());
    }

    public function testTheLocalMainWithoutARemote(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [new ProcessResult(1), new ProcessResult(0), new ProcessResult(0, "abc123\n")]);

        $tester->execute(['--changed' => '']);

        self::assertSame(['git rev-parse --verify --quiet origin/main', 'git rev-parse --verify --quiet main', 'git merge-base main HEAD'], \array_slice($this->runner->commands(), 0, 3));
        self::assertStringContainsString(' --diff-filter=AMR abc123 -- ', $this->runner->commands()[3]);
    }

    public function testMainWhenNoRefExists(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [new ProcessResult(1), new ProcessResult(1)]);

        self::assertSame(0, $tester->execute(['--changed' => null]));
        self::assertStringContainsString(' --diff-filter=AMR main -- ', $this->runner->commands()[2]);
        self::assertSame("No source file changed since main: nothing to mutate.\n", $tester->getDisplay());
    }
}
