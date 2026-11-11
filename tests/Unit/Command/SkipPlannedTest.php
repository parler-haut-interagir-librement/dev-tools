<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\CheckDocsCommand;
use Phil\DevTools\Command\WorkspaceCommand;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WorkspaceCommand::class)]
#[CoversClass(CheckDocsCommand::class)]
final class SkipPlannedTest extends CommandTestCase
{
    public function testThePlannedPackagesAreCheckedByDefault(): void
    {
        $root = $this->monorepo();
        $tester = $this->tester($root, 'check-docs');

        $status = $this->withEnvironment('PHIL_DEV_SKIP_PLANNED', null, static fn (): int => $tester->execute([], ['capture_stderr_separately' => true]));

        self::assertSame(1, $status);
        self::assertStringContainsString('packages/later: [missing-file] SECURITY.md', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
    }

    public function testTheOptionSkipsThemAndTellsHowMany(): void
    {
        $root = $this->monorepo();
        $tester = $this->tester($root, 'check-docs');

        [$status, $variable] = $this->withEnvironment('PHIL_DEV_SKIP_PLANNED', null, static fn (): array => [
            $tester->execute(['--skip-planned' => true], ['capture_stderr_separately' => true]),
            getenv('PHIL_DEV_SKIP_PLANNED'),
        ]);

        self::assertSame(0, $status);
        self::assertSame("The documentation passes every check.\n", $tester->getDisplay());
        self::assertSame("Skipped 2 planned packages: phil/later, phil/someday.\n", $tester->getErrorOutput());
        // The tools the command starts read the variable too.
        self::assertSame('1', $variable);
    }

    public function testTheVariableSkipsThem(): void
    {
        $root = $this->monorepo();
        unlink($root.'/packages/someday/phil-dev.php');
        $tester = $this->tester($root, 'check-docs');

        $status = $this->withEnvironment('PHIL_DEV_SKIP_PLANNED', '1', static fn (): int => $tester->execute([], ['capture_stderr_separately' => true]));

        self::assertSame(1, $status);
        self::assertStringNotContainsString('packages/later', $tester->getDisplay());
        self::assertStringContainsString('packages/someday', $tester->getDisplay());
        self::assertSame("Skipped 1 planned package: phil/later.\n", $tester->getErrorOutput());
    }

    public function testNoPlannedPackageToSkip(): void
    {
        $root = $this->temporaryDirectory();
        $this->tester($root, 'new')->execute(['package' => 'phil/alone', '--dir' => '.']);
        $tester = $this->tester($root, 'check-docs');

        $this->withEnvironment('PHIL_DEV_SKIP_PLANNED', null, static fn (): int => $tester->execute(['--skip-planned' => true]));

        self::assertSame("Skipped 0 planned packages.\nThe documentation passes every check.\n", $tester->getDisplay());
    }

    public function testADirectoryWithoutAWorkspaceIsNoError(): void
    {
        $root = $this->temporaryDirectory();
        $tester = $this->tester($root, 'new');

        $status = $this->withEnvironment('PHIL_DEV_SKIP_PLANNED', null, static fn (): int => $tester->execute(['package' => 'phil/alone', '--skip-planned' => true]));

        self::assertSame(0, $status);
        self::assertStringStartsWith('phil/alone written in alone:', $tester->getDisplay());
    }

    /**
     * A monorepo with one active member and two planned ones, `later` missing a file of its documentation.
     */
    private function monorepo(): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        mkdir($root.'/packages');

        foreach (['phil/active', 'phil/later', 'phil/someday'] as $package) {
            $this->tester($root, 'new')->execute(['package' => $package]);
        }

        $this->put($root.'/packages/later/phil-dev.php', "<?php return ['status' => 'planned'];");
        $this->put($root.'/packages/someday/phil-dev.php', "<?php return ['status' => 'planned'];");
        unlink($root.'/packages/later/SECURITY.md');
        unlink($root.'/packages/someday/SECURITY.md');

        return $root;
    }
}
