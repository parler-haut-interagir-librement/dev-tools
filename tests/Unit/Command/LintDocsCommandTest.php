<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\LintDocsCommand;
use Phil\DevTools\Docs\FrenchDictionary;
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

    public function testTheFrenchMirrorsAreReadInFrenchAndNotByVale(): void
    {
        $root = $this->package();
        $this->put($root.'/README.md', '# Example');
        $this->put($root.'/README.fr.md', '# Exemple');
        $path = $this->executables(['cspell', 'markdownlint-cli2', 'vale']);
        $tester = $this->tester($root, 'lint-docs', [new ProcessResult(0), new ProcessResult(0), new ProcessResult(0), new ProcessResult(0)]);

        self::assertSame(0, $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute([])));

        $docs = $root.'/var/phil-dev/docs';
        self::assertSame([
            $path.'/cspell lint --no-progress --config '.$docs.'/cspell-phil-example.json '.$root.'/README.md',
            $path.'/cspell lint --no-progress --config '.$docs.'/cspell-phil-example-fr.json '.$root.'/README.fr.md',
            $path.'/markdownlint-cli2 --config '.$docs.'/.markdownlint-cli2.jsonc '.$root.'/README.md '.$root.'/README.fr.md',
            $path.'/vale --config='.$docs.'/vale.ini '.$root.'/README.md',
        ], $this->runner->commands());
        self::assertStringContainsString('"language": "fr,en,en-GB"', (string) file_get_contents($docs.'/cspell-phil-example-fr.json'));
    }

    public function testEveryFrenchFileThroughNpx(): void
    {
        $root = $this->package();
        $this->put($root.'/README.md', '# Example');
        $this->put($root.'/README.fr.md', '# Exemple');
        $this->put($root.'/doc/fr/a.md', '# A');
        $path = $this->executables(['npx']);
        $tester = $this->tester($root, 'lint-docs', [new ProcessResult(0), new ProcessResult(0), new ProcessResult(0)]);

        self::assertSame(0, $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute([])));
        self::assertSame(
            $path.'/npx --yes cspell@10 lint --no-progress --config '.$root.'/var/phil-dev/docs/cspell-phil-example-fr.json '.$root.'/README.fr.md '.$root.'/doc/fr/a.md',
            $this->runner->commands()[1],
        );
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

    public function testTheDictionaryOfTheRootIsImported(): void
    {
        $root = $this->package();
        $this->put($root.'/README.md', '# Example');
        $this->put($root.'/README.fr.md', '# Exemple');
        $this->put($root.'/node_modules/@cspell/dict-fr-fr/cspell-ext.json', '{}');
        $path = $this->executables(['cspell', 'npm']);
        $tester = $this->tester($root, 'lint-docs');

        self::assertSame(0, $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute([])));

        $french = json_decode((string) file_get_contents($root.'/var/phil-dev/docs/cspell-phil-example-fr.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($french);
        self::assertSame([$root.'/node_modules/@cspell/dict-fr-fr/cspell-ext.json'], $french['import']);
        self::assertStringNotContainsString('npm', implode("\n", $this->runner->commands()));
        self::assertStringNotContainsString('"import"', (string) file_get_contents($root.'/var/phil-dev/docs/cspell-phil-example.json'));
    }

    public function testTheDictionaryIsInstalledOnDemandOnce(): void
    {
        $root = $this->fixture('monorepo');

        foreach (['slug', 'tilttek-dice'] as $member) {
            $this->put($root.'/packages/'.$member.'/README.md', '# Package');
            $this->put($root.'/packages/'.$member.'/README.fr.md', '# Paquet');
        }

        $path = $this->executables(['cspell', 'npm']);
        $tester = $this->tester($root, 'lint-docs');
        $installed = $root.'/var/phil-dev/node';
        $this->runner->onRun = function (array $command) use ($installed): void {
            if (str_ends_with($command[0], '/npm')) {
                $this->put($installed.'/node_modules/@cspell/dict-fr-fr/cspell-ext.json', '{}');
            }
        };

        self::assertSame(0, $this->withEnvironment('PATH', $path, static fn (): int => $tester->execute([])));

        $npm = array_values(array_filter($this->runner->commands(), static fn (string $command): bool => str_starts_with($command, $path.'/npm ')));
        self::assertSame([$path.'/npm install --prefix '.$installed.' --no-save --no-package-lock --ignore-scripts --no-audit --no-fund @cspell/dict-fr-fr@'.new FrenchDictionary()->version()], $npm);
        self::assertStringContainsString("== phil/slug\nInstalling @cspell/dict-fr-fr under var/phil-dev/node.\n", $tester->getDisplay());

        foreach (['phil-slug', 'tilttek-dice'] as $slug) {
            $french = json_decode((string) file_get_contents($root.'/var/phil-dev/docs/cspell-'.$slug.'-fr.json'), true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($french);
            self::assertSame([$installed.'/node_modules/@cspell/dict-fr-fr/cspell-ext.json'], $french['import']);
        }
    }

    public function testAPackageInFrenchAlsoNeedsTheDictionary(): void
    {
        $root = $this->package(['docs' => ['language' => 'fr']]);
        $this->put($root.'/README.md', '# Exemple');
        $this->put($root.'/var/phil-dev/node/node_modules/@cspell/dict-fr-fr/cspell-ext.json', '{}');
        $tester = $this->tester($root, 'lint-docs');

        self::assertSame(0, $this->withEnvironment('PATH', $this->executables(['cspell']), static fn (): int => $tester->execute([])));

        $config = json_decode((string) file_get_contents($root.'/var/phil-dev/docs/cspell-phil-example.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        self::assertSame([$root.'/var/phil-dev/node/node_modules/@cspell/dict-fr-fr/cspell-ext.json'], $config['import']);
    }

    public function testWithoutNpmCspellLooksForItItself(): void
    {
        $root = $this->package();
        $this->put($root.'/README.md', '# Example');
        $this->put($root.'/README.fr.md', '# Exemple');
        $tester = $this->tester($root, 'lint-docs');

        self::assertSame(0, $this->withEnvironment('PATH', $this->executables(['cspell']), static fn (): int => $tester->execute([])));

        self::assertStringContainsString("== phil/example\n@cspell/dict-fr-fr is not installed: cspell looks for it itself.\n", $tester->getDisplay());
        $french = json_decode((string) file_get_contents($root.'/var/phil-dev/docs/cspell-phil-example-fr.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($french);
        self::assertSame(['@cspell/dict-fr-fr/cspell-ext.json'], $french['import']);
    }

    public function testAnEnglishPackageNeedsNoDictionary(): void
    {
        $root = $this->package();
        $this->put($root.'/README.md', '# Example');
        $tester = $this->tester($root, 'lint-docs');

        self::assertSame(0, $this->withEnvironment('PATH', $this->executables(['cspell', 'npm']), static fn (): int => $tester->execute([])));

        self::assertCount(1, $this->runner->runs);
        self::assertStringEndsWith("is not installed: skipped.\n== phil/example\n", $tester->getDisplay());
    }
}
