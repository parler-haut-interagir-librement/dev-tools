<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\InfectionCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(InfectionCommand::class)]
final class InfectionCommandTest extends CommandTestCase
{
    public function testTheWholeCode(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [new ProcessResult(3)]);

        self::assertSame(3, $tester->execute(['arguments' => ['--filter=X']]));
        self::assertSame([\PHP_BINARY.' -d memory_limit=4G '.$root.'/vendor-bin/infection/vendor/bin/infection --configuration='.$root.'/var/phil-dev/infection.json --no-progress --show-mutations --threads=max --filter=X'], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[0]['directory']);
        self::assertSame(['TMPDIR' => realpath($root).'/var/tmp'], $this->runner->runs[0]['environment']);
        self::assertStringContainsString('"minCoveredMsi": 100', (string) file_get_contents($root.'/var/phil-dev/infection.json'));
    }

    public function testFromTheDirectoryOfAMemberItRunsFromTheMonorepoOnThatMemberAlone(): void
    {
        $root = (string) realpath($this->fixture('monorepo'));
        $tester = $this->tester($root.'/packages/slug', 'infection', [new ProcessResult(0)]);

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Running from the monorepo at '.$root.', on ', $tester->getDisplay());
        self::assertSame([\PHP_BINARY.' -d memory_limit=4G '.$root.'/vendor-bin/infection/vendor/bin/infection --configuration='.$root.'/var/phil-dev/infection.json --no-progress --show-mutations --threads=max'], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[0]['directory']);
        $config = (string) file_get_contents($root.'/var/phil-dev/infection.json');
        self::assertStringContainsString($root.'/packages/slug/src', $config);
        self::assertStringNotContainsString($root.'/packages/slug-bundle/src', $config);
        self::assertFileDoesNotExist($root.'/packages/slug/var/phil-dev/infection.json');
    }

    public function testAMemberWithItsOwnInfectionStaysWhereItIs(): void
    {
        $root = (string) realpath($this->fixture('monorepo'));
        $this->put($root.'/packages/slug/vendor-bin/infection/vendor/bin/infection', '');
        $tester = $this->tester($root.'/packages/slug', 'infection', [new ProcessResult(0)]);

        self::assertSame(0, $tester->execute([]));
        self::assertSame($root.'/packages/slug', $this->runner->runs[0]['directory']);
    }

    public function testAPackageOutsideAnyMonorepoStaysWhereItIs(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'infection', [new ProcessResult(0)]);

        self::assertSame(0, $tester->execute([]));
        self::assertStringNotContainsString('Running from the monorepo', $tester->getDisplay());
        self::assertSame($root, $this->runner->runs[0]['directory']);
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
        self::assertSame("Mutating the 3 source files changed since release/1.0.\nWith the tests of phil/example-monorepo, phil/slug.\n", $tester->getDisplay());
        self::assertSame([
            'git merge-base release/1.0 HEAD',
            'git diff --name-only --diff-filter=AMR abc123 -- '.$directories,
            'git ls-files --others --exclude-standard -- '.$directories,
            \PHP_BINARY.' -d memory_limit=4G '.$root.'/vendor-bin/infection/vendor/bin/infection --configuration='.$root.'/var/phil-dev/infection.json --no-progress --show-mutations --threads=max --ignore-msi-with-no-mutations '.$root.'/packages/slug/src/B.php '.$root.'/packages/slug/src/C.php '.$root.'/src/A.php',
        ], $this->runner->commands());
        self::assertSame([true, true, true, false], array_column($this->runner->runs, 'capture'));
        self::assertSame([$root, $root, $root, $root], array_column($this->runner->runs, 'directory'));
    }

    public function testAnUntrackedFileAlone(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [new ProcessResult(0, "abc123\n"), new ProcessResult(0), new ProcessResult(0, "src/New.php\n")]);

        self::assertSame(0, $tester->execute(['--changed' => 'main', 'arguments' => ['--mutators=Plus']]));
        self::assertStringEndsWith(' --threads=max --ignore-msi-with-no-mutations --mutators=Plus '.$root.'/src/New.php', $this->runner->commands()[3]);
    }

    public function testTheChangedAndTheNewFilesAreSplitOnTheirOwnLines(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [new ProcessResult(0, "abc123\n"), new ProcessResult(0, 'src/A.php'), new ProcessResult(0, 'src/B.php')]);

        self::assertSame(0, $tester->execute(['--changed' => 'main']));
        self::assertSame("Mutating the 2 source files changed since main.\nWith the tests of phil/example-monorepo.\n", $tester->getDisplay());
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
        self::assertSame("Mutating the 1 source files changed since develop.\nWith the tests of phil/example-monorepo.\n", $tester->getDisplay());
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
        self::assertSame("Mutating the 1 source files changed since main.\nWith the tests of phil/example-monorepo.\n", $tester->getDisplay());
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
        self::assertSame("Mutating the 1 source files changed since origin/main.\nWith the tests of phil/example-monorepo.\n", $tester->getDisplay());
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

    public function testTheChangedFilesNarrowTheTestsToTheirPackages(): void
    {
        $root = (string) realpath($this->fixture('monorepo'));
        $this->put($root.'/phpunit.dist.xml', '<phpunit><testsuites><testsuite name="Unit"><directory>packages/*/tests</directory></testsuite></testsuites><source><include><directory>packages/*/src</directory></include></source></phpunit>');
        $this->put($root.'/phil-dev.php', "<?php return ['infection' => ['memoryLimit' => '6G']];");
        $tester = $this->tester($root, 'infection', [new ProcessResult(0, "abc123\n"), new ProcessResult(0, "packages/slug/src/C.php\n"), new ProcessResult(0)]);

        $this->withEnvironment('PHIL_DEV_SKIP_PLANNED', null, static fn (): int => $tester->execute(['--changed' => 'main']));

        self::assertSame("Mutating the 1 source files changed since main.\nWith the tests of phil/slug.\n", $tester->getDisplay());
        self::assertSame(\PHP_BINARY.' -d memory_limit=6G '.$root.'/vendor-bin/infection/vendor/bin/infection --configuration='.$root.'/var/phil-dev/infection.json --no-progress --show-mutations --threads=max --ignore-msi-with-no-mutations '.$root.'/packages/slug/src/C.php', $this->runner->commands()[3]);
        $config = json_decode((string) file_get_contents($root.'/var/phil-dev/infection.json'), true);
        self::assertIsArray($config);
        self::assertSame(['directories' => [$root.'/packages/slug/src']], $config['source']);
        self::assertSame(['configDir' => $root.'/var/phil-dev/phpunit'], $config['phpUnit']);
        self::assertSame('-d memory_limit=6G', $config['initialTestsPhpOptions']);
        $phpUnit = (string) file_get_contents($root.'/var/phil-dev/phpunit/phpunit.xml');
        self::assertStringContainsString('<directory>'.$root.'/packages/slug/tests</directory>', $phpUnit);
        self::assertStringNotContainsString($root.'/packages/slug-bundle/tests', $phpUnit);
        self::assertStringContainsString('<exclude>'.$root.'/packages/slug-bundle</exclude>', $phpUnit);
        self::assertStringContainsString('<exclude>'.$root.'/packages/tilttek-dice</exclude>', $phpUnit);
        self::assertStringNotContainsString('<exclude>'.$root.'/packages/slug</exclude>', $phpUnit);
    }

    public function testAFileOfAMemberWhoseNameStartsLikeAnotherBelongsToItsOwnMember(): void
    {
        $root = (string) realpath($this->fixture('monorepo'));
        $tester = $this->tester($root, 'infection', [new ProcessResult(0, "abc123\n"), new ProcessResult(0, "packages/slug-bundle/src/D.php\n"), new ProcessResult(0)]);

        self::assertSame(0, $tester->execute(['--changed' => 'main']));
        self::assertSame("Mutating the 1 source files changed since main.\nWith the tests of phil/slug-bundle.\n", $tester->getDisplay());
    }

    public function testAPackageOnItsOwnKeepsItsWholeSuite(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'infection', [new ProcessResult(0, "abc123\n"), new ProcessResult(0, "src/A.php\n"), new ProcessResult(0)]);

        self::assertSame(0, $tester->execute(['--changed' => 'main']));
        self::assertSame("Mutating the 1 source files changed since main.\n", $tester->getDisplay());
        self::assertStringStartsWith(\PHP_BINARY.' -d memory_limit=4G ', $this->runner->commands()[3]);
    }

    public function testEachChangedFileIsAnAbsoluteArgumentOfItsOwn(): void
    {
        $root = (string) realpath($this->fixture('monorepo'));
        $tester = $this->tester($root, 'infection', [new ProcessResult(0, "abc123\n"), new ProcessResult(0, "packages/slug/src/C.php\npackages/slug/src/B.php\n"), new ProcessResult(0)]);

        self::assertSame(0, $tester->execute(['--changed' => 'main']));
        self::assertSame([$root.'/packages/slug/src/B.php', $root.'/packages/slug/src/C.php'], \array_slice($this->runner->runs[3]['command'], -2));
        self::assertSame('--ignore-msi-with-no-mutations', $this->runner->runs[3]['command'][8]);
    }

    public function testAChangedFileOutsideTheDeclaredDirectoriesIsNotPassed(): void
    {
        $root = (string) realpath($this->fixture('monorepo'));
        $this->put($root.'/packages/slug/src/Kept/.gitkeep', '');
        $this->put($root.'/packages/slug/phil-dev.php', "<?php return ['infection' => ['directories' => ['src/Kept']]];");
        $tester = $this->tester($root, 'infection', [new ProcessResult(0, "abc123\n"), new ProcessResult(0, "packages/slug/src/Kept/A.php\npackages/slug/src/Left.php\npackages/slug/src/KeptToo.php\n"), new ProcessResult(0)]);

        self::assertSame(0, $tester->execute(['--changed' => 'main']));
        self::assertSame("Mutating the 1 source files changed since main.\nWith the tests of phil/slug.\n", $tester->getDisplay());
        self::assertSame($root.'/packages/slug/src/Kept/A.php', array_last($this->runner->runs[3]['command']));
        self::assertSame('--ignore-msi-with-no-mutations', $this->runner->runs[3]['command'][8]);
    }

    public function testOnlyFilesOfASkippedPlannedPackageLeaveNothingToMutate(): void
    {
        $root = (string) realpath($this->fixture('monorepo'));
        $this->put($root.'/packages/slug/phil-dev.php', "<?php return ['status' => 'planned'];");
        $tester = $this->tester($root, 'infection', [new ProcessResult(0, "abc123\n"), new ProcessResult(0, "packages/slug/src/C.php\n"), new ProcessResult(0)]);

        $this->withEnvironment('PHIL_DEV_SKIP_PLANNED', '1', static fn (): int => $tester->execute(['--changed' => 'main']));

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame("Skipped 1 planned package: phil/slug.\nNo file changed since main is in a source directory Infection mutates: nothing to mutate.\n", $tester->getDisplay());
        self::assertCount(3, $this->runner->runs);
    }

    /**
     * @return iterable<string, array{ProcessResult, ProcessResult}>
     */
    public static function provideAGitFailureFailsInsteadOfPassingCases(): iterable
    {
        yield 'the diff' => [new ProcessResult(128), new ProcessResult(0, "src/A.php\n")];
        yield 'the untracked files' => [new ProcessResult(0, "src/A.php\n"), new ProcessResult(128)];
    }

    #[DataProvider('provideAGitFailureFailsInsteadOfPassingCases')]
    public function testAGitFailureFailsInsteadOfPassing(ProcessResult $diff, ProcessResult $untracked): void
    {
        $root = (string) realpath($this->fixture('monorepo'));
        $tester = $this->tester($root, 'infection', [new ProcessResult(0, "abc123\n"), $diff, $untracked]);

        self::assertSame(1, $tester->execute(['--changed' => 'main']));
        self::assertSame('git could not list the files changed since main in '.$root.": nothing was mutated.\n", $tester->getDisplay());
        self::assertCount(3, $this->runner->runs);
    }
}
