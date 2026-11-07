<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\InitCommand;
use Phil\DevTools\Command\WorkspaceCommand;
use Phil\DevTools\Console\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(InitCommand::class)]
#[CoversClass(WorkspaceCommand::class)]
final class InitCommandTest extends CommandTestCase
{
    public function testAPackage(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');
        $tester = $this->tester($root, 'init');

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('created    phpstan.neon', $tester->getDisplay());
        self::assertStringContainsString('created    composer-dependency-analyser.php', $tester->getDisplay());
        self::assertStringContainsString('Done. Run composer update to install the tools.', $tester->getDisplay());
        self::assertStringNotContainsString('packages/*', (string) file_get_contents($root.'/phil-dev.php'));
    }

    public function testAMonorepoIsFoundByItsPackagesDirectory(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        mkdir($root.'/packages');

        self::assertSame(0, $this->tester($root, 'init')->execute([]));
        self::assertStringContainsString('packages/*', (string) file_get_contents($root.'/phil-dev.php'));
        self::assertFileDoesNotExist($root.'/composer-dependency-analyser.php');
    }

    public function testTheOptionsChooseTheKind(): void
    {
        $package = $this->temporaryDirectory();
        $this->put($package.'/composer.json', '{"name": "phil/x"}');
        mkdir($package.'/packages');
        $monorepo = $this->temporaryDirectory();
        $this->put($monorepo.'/composer.json', '{"name": "phil/root"}');

        $this->tester($package, 'init')->execute(['--package' => true]);
        $this->tester($monorepo, 'init')->execute(['--monorepo' => true]);

        self::assertStringNotContainsString('packages/*', (string) file_get_contents($package.'/phil-dev.php'));
        self::assertStringContainsString('packages/*', (string) file_get_contents($monorepo.'/phil-dev.php'));
    }

    public function testWhatDiffersIsKeptUnlessForced(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x", "scripts": {"cs": "mine"}}');
        $this->put($root.'/rector.php', '<?php // mine');

        $tester = $this->tester($root, 'init');
        $tester->execute([]);

        self::assertStringContainsString('kept       rector.php', $tester->getDisplay());
        self::assertStringContainsString('scripts.cs differs, kept', $tester->getDisplay());
        self::assertStringContainsString('run again with --force', $tester->getDisplay());

        $forced = $this->tester($root, 'init');
        $forced->execute(['--force' => true]);

        self::assertStringContainsString('updated    rector.php', $forced->getDisplay());
        self::assertStringContainsString('Done.', $forced->getDisplay());
    }

    public function testAFileKeptIsReported(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');
        $this->put($root.'/.php-cs-fixer.dist.php', '<?php // mine');

        $tester = $this->tester($root, 'init');
        $tester->execute([]);

        self::assertStringEndsWith("updated    composer.json\n\nSome files or values differ and were kept; run again with --force to replace them.\n", $tester->getDisplay());
    }

    public function testTheDisplay(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');

        $tester = $this->tester($root, 'init');
        $tester->execute([]);

        self::assertStringEndsWith("updated    composer.json\n\nDone. Run composer update to install the tools.\n", $tester->getDisplay());
    }

    public function testTheRootComesFromTheEnvironment(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/x"}');
        putenv('PHIL_DEV_ROOT='.$root);

        try {
            $tester = new CommandTester(new Application()->find('init'));
            $tester->execute([]);
        } finally {
            putenv('PHIL_DEV_ROOT');
        }

        self::assertFileExists($root.'/phpstan.neon');
    }
}
