<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\LintDocsCommand;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LintDocsCommand::class)]
final class LintDocsCommandTest extends CommandTestCase
{
    public function testEveryLinterOnThePath(): void
    {
        $root = $this->package();
        $this->put($root.'/README.md', '# Example');
        $this->put($root.'/doc/usage.md', '# Usage');
        $path = $this->executables(['cspell', 'markdownlint-cli2', 'vale']);
        $tester = $this->tester($root, 'lint-docs', [new ProcessResult(0), new ProcessResult(1), new ProcessResult(0)]);

        self::assertSame(1, $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute([])));

        $files = $root.'/README.md '.$root.'/doc/usage.md';
        $docs = $root.'/var/phil-dev/docs';
        self::assertSame([
            $path.'/cspell lint --no-progress --config '.$docs.'/cspell-phil-example.json '.$files,
            $path.'/markdownlint-cli2 --config '.$docs.'/.markdownlint-cli2.jsonc '.$files,
            $path.'/vale --config='.$docs.'/vale.ini '.$files,
        ], $this->runner->commands());
        self::assertSame([$root, $root, $root], array_column($this->runner->runs, 'directory'));
        self::assertSame("== phil/example\n", $tester->getDisplay());
        self::assertFileExists($docs.'/vale.ini');
        self::assertStringContainsString('"language": "en,en-GB"', (string) file_get_contents($docs.'/cspell-phil-example.json'));
    }

    public function testNpxStandsInAndValeIsSkipped(): void
    {
        $root = $this->package(['docs' => ['language' => 'fr']]);
        $this->put($root.'/README.md', '# Exemple');
        $path = $this->executables(['npx', 'vale']);
        $tester = $this->tester($root, 'lint-docs');

        self::assertSame(0, $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute([])));
        self::assertSame([
            $path.'/npx --yes cspell@10 lint --no-progress --config '.$root.'/var/phil-dev/docs/cspell-phil-example.json '.$root.'/README.md',
            $path.'/npx --yes markdownlint-cli2@0.24 --config '.$root.'/var/phil-dev/docs/.markdownlint-cli2.jsonc '.$root.'/README.md',
        ], $this->runner->commands());
    }

    public function testNothingInstalled(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/packages/tilttek-dice/README.md', '# Dice');
        $tester = $this->tester($root, 'lint-docs');

        self::assertSame(0, $this->withEnvironment('PATH', '', static fn (): int => $tester->execute([])));
        self::assertSame("cspell is not installed: skipped.\nmarkdownlint-cli2 is not installed: skipped.\nvale is not installed: skipped.\n== tilttek/dice\n", $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }
}
