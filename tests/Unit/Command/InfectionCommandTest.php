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
        self::assertStringContainsString('"minCoveredMsi": 100', (string) file_get_contents($root.'/var/phil-dev/infection.json'));
    }

    public function testTheFilesChangedSinceAGivenRef(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [
            new ProcessResult(0, "abc123\n"),
            new ProcessResult(0, "src/A.php\npackages/slug/src/B.php\npackages/slug/README.md\n"),
            new ProcessResult(0),
        ]);

        self::assertSame(0, $tester->execute(['--changed' => 'release/1.0']));
        self::assertSame("Mutating the 2 source files changed since release/1.0.\n", $tester->getDisplay());
        self::assertSame([
            'git merge-base release/1.0 HEAD',
            'git diff --name-only --diff-filter=AM abc123 -- '.$root.'/src '.$root.'/packages/slug/src '.$root.'/packages/slug-bundle/src '.$root.'/packages/tilttek-dice/src',
            $root.'/vendor-bin/infection/vendor/bin/infection --configuration='.$root.'/var/phil-dev/infection.json --no-progress --show-mutations --threads=max --filter=src/A.php,packages/slug/src/B.php',
        ], $this->runner->commands());
        self::assertSame([true, true, false], array_column($this->runner->runs, 'capture'));
        self::assertSame([$root, $root, $root], array_column($this->runner->runs, 'directory'));
    }

    public function testNothingChanged(): void
    {
        $root = $this->fixture('monorepo');
        $tester = $this->tester($root, 'infection', [new ProcessResult(128), new ProcessResult(0, "\n")]);

        self::assertSame(0, $tester->execute(['--changed' => 'nowhere']));
        self::assertSame("No source file changed since nowhere: nothing to mutate.\n", $tester->getDisplay());
        self::assertStringContainsString(' --diff-filter=AM nowhere -- ', $this->runner->commands()[1]);
        self::assertCount(2, $this->runner->runs);
    }

    public function testTheBaseRefFromTheVariable(): void
    {
        $root = $this->fixture('monorepo');
        putenv('PHIL_DEV_BASE_REF=develop');

        try {
            $this->tester($root, 'infection')->execute(['--changed' => null]);
        } finally {
            putenv('PHIL_DEV_BASE_REF');
        }

        self::assertSame('git merge-base develop HEAD', $this->runner->commands()[0]);
    }

    public function testTheBaseRefIsTheRemoteMainWhenItExists(): void
    {
        $root = $this->fixture('monorepo');

        $this->tester($root, 'infection', [new ProcessResult(0)])->execute(['--changed' => null]);

        self::assertSame('git rev-parse --verify --quiet origin/main', $this->runner->commands()[0]);
        self::assertTrue($this->runner->runs[0]['capture']);
        self::assertSame('git merge-base origin/main HEAD', $this->runner->commands()[1]);
    }

    public function testTheBaseRefIsMainOtherwise(): void
    {
        $root = $this->fixture('monorepo');

        $this->tester($root, 'infection', [new ProcessResult(1)])->execute(['--changed' => null]);

        self::assertSame('git merge-base main HEAD', $this->runner->commands()[1]);
    }
}
