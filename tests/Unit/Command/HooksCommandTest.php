<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\HooksCommand;
use Phil\DevTools\Config\CaptainHookConfig;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HooksCommand::class)]
final class HooksCommandTest extends CommandTestCase
{
    public function testItInstallsTheHooksInTheSharedGitDirectory(): void
    {
        $root = $this->checkout();
        $tester = $this->tester($root, 'hooks', [new ProcessResult(0, "/repository/.git\n"), new ProcessResult(0)]);

        self::assertSame(0, $tester->execute([]));
        self::assertSame([
            'git rev-parse --path-format=absolute --git-common-dir',
            $root.'/vendor-bin/captainhook/vendor/bin/captainhook install --force --configuration=captainhook.json --git-directory=/repository/.git',
        ], $this->runner->commands());
        self::assertTrue($this->runner->runs[0]['capture']);
        self::assertSame($root, $this->runner->runs[0]['directory']);
        self::assertSame($root, $this->runner->runs[1]['directory']);
    }

    public function testItReturnsTheExitCodeOfCaptainHook(): void
    {
        $tester = $this->tester($this->checkout(), 'hooks', [new ProcessResult(0, '/repository/.git'), new ProcessResult(3)]);

        self::assertSame(3, $tester->execute([]));
    }

    public function testItRefusesADirectoryThatIsNoCheckout(): void
    {
        foreach ([new ProcessResult(128, '/x'), new ProcessResult(0, " \n")] as $result) {
            $tester = $this->tester($this->checkout(), 'hooks', [$result]);

            self::assertSame(1, $tester->execute([]));
            self::assertStringContainsString('This directory is not a git checkout.', $tester->getDisplay());
            self::assertCount(1, $this->runner->runs);
        }
    }

    public function testTheCheckOnlyChecks(): void
    {
        $tester = $this->tester($this->checkout(), 'hooks');

        self::assertSame(0, $tester->execute(['--check' => true]));
        self::assertSame("The hooks can run in this checkout.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testItNamesEachMissingTool(): void
    {
        $root = $this->checkout(['vendor-bin/captainhook/vendor/bin/captainhook']);
        $tester = $this->tester($root, 'hooks');

        self::assertSame(1, $tester->execute(['--check' => true]));
        self::assertSame(
            "The hooks cannot run in this checkout.\n"
            ."vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer is missing: composer bin php-cs-fixer install\n"
            ."vendor-bin/phpstan/vendor/bin/phpstan is missing: composer bin phpstan install\n"
            ."vendor-bin/twig/vendor/bin/twig-cs-fixer is missing: composer bin twig install\n",
            $tester->getDisplay(),
        );
        self::assertSame([], $this->runner->runs);

        self::assertSame(1, $this->tester($root, 'hooks')->execute([]));
        self::assertSame([], $this->runner->runs);
    }

    public function testItRefusesAMissingOrBrokenConfiguration(): void
    {
        $root = $this->package();

        foreach (['', '{', '"text"', null] as $content) {
            if (null !== $content) {
                $this->put($root.'/captainhook.json', $content);
            }

            $tester = $this->tester($root, 'hooks');

            self::assertSame(1, $tester->execute([]));
            self::assertStringContainsString('captainhook.json is missing or is not a JSON object: phil-dev init writes it.', $tester->getDisplay());
            self::assertSame([], $this->runner->runs);

            if (null === $content) {
                break;
            }
        }
    }

    public function testCaptainHookAtThePathTheConfigurationGives(): void
    {
        $root = $this->package();
        $this->put($root.'/captainhook.json', '{"config": {"run": {"path": "tools/captainhook"}}, "pre-commit": {"actions": [{"action": "make lint"}]}}');
        $this->put($root.'/tools/captainhook', '');

        $tester = $this->tester($root, 'hooks', [new ProcessResult(0, '/r/.git')]);

        self::assertSame(0, $tester->execute([]));
        self::assertSame($root.'/tools/captainhook install --force --configuration=captainhook.json --git-directory=/r/.git', $this->runner->commands()[1]);
    }

    public function testCaptainHookAtAnAbsolutePath(): void
    {
        $root = $this->package();
        $this->put($root.'/bin/captainhook', '');
        $this->put($root.'/captainhook.json', json_encode(['config' => ['run' => ['path' => $root.'/bin/captainhook']]], \JSON_THROW_ON_ERROR));

        $tester = $this->tester($root, 'hooks', [new ProcessResult(0, '/r/.git')]);

        self::assertSame(0, $tester->execute([]));
        self::assertSame($root.'/bin/captainhook install --force --configuration=captainhook.json --git-directory=/r/.git', $this->runner->commands()[1]);
    }

    public function testTheToolsOfAConfiguration(): void
    {
        self::assertSame([
            'vendor-bin/captainhook/vendor/bin/captainhook' => 'captainhook',
            'vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer' => 'php-cs-fixer',
            'vendor-bin/phpstan/vendor/bin/phpstan' => 'phpstan',
            'vendor-bin/twig/vendor/bin/twig-cs-fixer' => 'twig',
        ], HooksCommand::tools(new CaptainHookConfig('vendor/bin/phil-dev')->build()));

        self::assertSame(['vendor-bin/captainhook/vendor/bin/captainhook' => 'captainhook'], HooksCommand::tools([
            'config' => ['run' => ['path' => '']],
            'pre-commit' => ['actions' => [['action' => 'php bin/phil-dev staged unknown'], ['action' => 3], 'text', ['action' => 'unstaged phpstan']]],
            'pre-push' => ['actions' => 'none'],
            'commit-msg' => 'none',
        ]));
        self::assertSame(['tools/captainhook' => 'captainhook'], HooksCommand::tools(['config' => ['run' => ['path' => 'tools/captainhook']]]));
        self::assertSame(['vendor-bin/hooks/vendor/bin/captainhook' => 'hooks'], HooksCommand::tools(['config' => ['run' => ['path' => 'vendor-bin/hooks/vendor/bin/captainhook']]]));
        self::assertSame(['vendor-bin/captainhook/vendor/bin/captainhook' => 'captainhook', 'vendor-bin/phpstan/vendor/bin/phpstan' => 'phpstan'], HooksCommand::tools(['pre-commit' => ['actions' => [['action' => 'php vendor/bin/phil-dev staged  phpstan --in-dir=packages/']]]]));
    }

    /**
     * A package whose `captainhook.json` is the one `init` writes, with the tools it calls installed but those named.
     *
     * @param list<string> $installed the binaries installed, all of them when null
     */
    private function checkout(?array $installed = null): string
    {
        $root = $this->package();
        $this->put($root.'/captainhook.json', new CaptainHookConfig('vendor/bin/phil-dev')->json());

        foreach ($installed ?? array_keys(HooksCommand::tools(new CaptainHookConfig('vendor/bin/phil-dev')->build())) as $binary) {
            $this->put($root.'/'.$binary, '');
        }

        return $root;
    }
}
