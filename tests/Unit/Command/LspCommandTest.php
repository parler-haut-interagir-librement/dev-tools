<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\LspCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LspCommand::class)]
final class LspCommandTest extends CommandTestCase
{
    private const string CODES = "config.unknown_key\n  route.not_found\r\n\nSome note\nsee config.duplicate_key\nroute.missing_parameters below\ntemplate.not_found\n";

    public function testEachApplicationWithEveryCodeBlocking(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/bin/console', '');
        $this->put($root.'/packages/tilttek-dice/composer.json', '{"name": "tilttek/dice-site", "type": "project"}');
        $this->put($root.'/packages/tilttek-dice/bin/console', '');
        $path = $this->executables(['symfony']);
        $tester = $this->tester($root, 'lsp', [new ProcessResult(0, self::CODES), new ProcessResult(0), new ProcessResult(1)]);

        $status = $this->withEnvironment('GITHUB_ACTIONS', null, fn (): int => $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute(['arguments' => ['--source-only']])));

        self::assertSame(1, $status);
        self::assertSame([
            $path.'/symfony lsp:check --list-codes',
            $path.'/symfony lsp:check --format=human --fail-on=config.unknown_key,route.not_found,template.not_found --source-only',
            $path.'/symfony lsp:check --format=human --fail-on=config.unknown_key,route.not_found,template.not_found --source-only',
        ], $this->runner->commands());
        self::assertSame($root, $this->runner->runs[1]['directory']);
        self::assertSame($root.'/packages/tilttek-dice', $this->runner->runs[2]['directory']);
        self::assertStringContainsString("packages/slug-bundle: a symfony-bundle, not an application: skipped.\n== the root\n== packages/tilttek-dice\n", $tester->getDisplay());
    }

    public function testTheGitHubFormatUnderGitHubActions(): void
    {
        $root = $this->package([], ['type' => 'project']);
        $this->put($root.'/bin/console', '');
        $path = $this->executables(['symfony']);
        $tester = $this->tester($root, 'lsp', [new ProcessResult(0, self::CODES)]);

        $status = $this->withEnvironment('GITHUB_ACTIONS', 'true', fn (): int => $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute([])));

        self::assertSame(0, $status);
        self::assertStringStartsWith($path.'/symfony lsp:check --format=github ', $this->runner->commands()[1]);
    }

    public function testNoApplication(): void
    {
        $tester = $this->tester($this->fixture('monorepo'), 'lsp');

        self::assertSame(0, $tester->execute([]));
        self::assertSame("packages/slug-bundle: a symfony-bundle, not an application: skipped.\nNo Symfony application (bin/console): nothing to check.\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testWithoutTheSymfonyCli(): void
    {
        $root = $this->package([], ['type' => 'project']);
        $this->put($root.'/bin/console', '');
        $tester = $this->tester($root, 'lsp');

        self::assertSame(1, $this->withEnvironment('PATH', $this->executables([]), static fn (): int => $tester->execute([])));
        self::assertStringContainsString('The Symfony CLI (symfony) is not on the PATH', $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testNoCodeListed(): void
    {
        $root = $this->package([], ['type' => 'project']);
        $this->put($root.'/bin/console', '');
        $tester = $this->tester($root, 'lsp', [new ProcessResult(0, "nothing here\n")]);

        self::assertSame(1, $this->withEnvironment('PATH', $this->executables(['symfony']), static fn (): int => $tester->execute([])));
        self::assertStringContainsString('listed no diagnostic code', $tester->getDisplay());
        self::assertCount(1, $this->runner->runs);
    }

    public function testTheListingFails(): void
    {
        $root = $this->package([], ['type' => 'project']);
        $this->put($root.'/bin/console', '');
        $tester = $this->tester($root, 'lsp', [new ProcessResult(1, self::CODES)]);

        self::assertSame(1, $this->withEnvironment('PATH', $this->executables(['symfony']), static fn (): int => $tester->execute([])));
        self::assertCount(1, $this->runner->runs);
    }

    public function testTurnedOff(): void
    {
        $root = $this->package(['gates' => ['lsp' => false]], ['type' => 'project']);
        $this->put($root.'/bin/console', '');
        $tester = $this->tester($root, 'lsp');

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('The lsp gate is turned off', $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }
}
