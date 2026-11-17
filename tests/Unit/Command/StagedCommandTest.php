<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Phil\DevTools\Command\StagedCommand;
use Phil\DevTools\Console\Application;
use Phil\DevTools\Process\LocalProcessRunner;
use Phil\DevTools\Process\ProcessResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(StagedCommand::class)]
final class StagedCommandTest extends CommandTestCase
{
    public function testThePhpFilesStagedAreArgumentsOfTheirOwn(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'staged', [new ProcessResult(0, "src/A.php\0a b; touch pwned.php\0README.md\0-x.php\0"), new ProcessResult(4)]);

        self::assertSame(4, $tester->execute(['tool' => 'php-cs-fixer']));
        self::assertSame(['git', 'diff', '--cached', '--name-only', '-z', '--diff-filter=ACMR'], $this->runner->runs[0]['command']);
        self::assertSame([$root.'/vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer', 'check', '--diff', '--path-mode=intersection', '--', 'src/A.php', 'a b; touch pwned.php', '-x.php'], $this->runner->runs[1]['command']);
        self::assertFalse($this->runner->runs[1]['capture']);
        self::assertSame($root, $this->runner->runs[1]['directory']);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function phpCsFixerConfigurations(): iterable
    {
        yield 'the local one first' => [['.php-cs-fixer.php', '.php-cs-fixer.dist.php'], '.php-cs-fixer.php'];
        yield 'the distributed one' => [['.php-cs-fixer.dist.php'], '.php-cs-fixer.dist.php'];
    }

    /**
     * PHP-CS-Fixer refuses several paths without its configuration.
     *
     * @param list<string> $files
     */
    #[DataProvider('phpCsFixerConfigurations')]
    public function testTheConfigurationOfPhpCsFixer(array $files, string $expected): void
    {
        $root = $this->package();

        foreach ($files as $file) {
            $this->put($root.'/'.$file, '<?php');
        }

        $this->tester($root, 'staged', [new ProcessResult(0, "src/A.php\0src/B.php\0")])->execute(['tool' => 'php-cs-fixer']);

        self::assertSame(['--path-mode=intersection', '--config='.$root.'/'.$expected, '--', 'src/A.php', 'src/B.php'], \array_slice($this->runner->runs[1]['command'], 3));
    }

    public function testTheTemplatesForTwigCsFixer(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'staged', [new ProcessResult(0, "src/A.php\0templates/a.html.twig\0templates/notatwig\0")]);

        self::assertSame(0, $tester->execute(['tool' => 'twig-cs-fixer']));
        self::assertSame([$root.'/vendor-bin/twig/vendor/bin/twig-cs-fixer', 'lint', '--no-cache', '--', 'templates/a.html.twig'], $this->runner->runs[1]['command']);
    }

    public function testOnlyUnderTheDirectoriesGiven(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'staged', [new ProcessResult(0, "rector.php\0packages/a/src/A.php\0packagesX/B.php\0lib/C.php\0")]);

        $tester->execute(['tool' => 'php-cs-fixer', '--in-dir' => ['packages', 'lib/']]);

        self::assertSame(['--', 'packages/a/src/A.php', 'lib/C.php'], \array_slice($this->runner->runs[1]['command'], 4));
    }

    public function testNothingStagedOfTheType(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'staged', [new ProcessResult(0, "README.md\0")]);

        self::assertSame(0, $tester->execute(['tool' => 'phpstan']));
        self::assertStringContainsString('No staged .php file: nothing to check.', $tester->getDisplay());
        self::assertCount(1, $this->runner->runs);
    }

    public function testAnUnknownTool(): void
    {
        $tester = $this->tester($this->package(), 'staged');

        self::assertSame(Command::INVALID, $tester->execute(['tool' => 'rector']));
        self::assertStringContainsString('Unknown tool rector', $tester->getDisplay());
        self::assertSame([], $this->runner->runs);
    }

    public function testPhpStanIsCapturedWithItsErrors(): void
    {
        $root = $this->package();
        $tester = $this->tester($root, 'staged', [new ProcessResult(0, "src/A.php\0"), new ProcessResult(1, " [ERROR] Found 1 error\n")]);

        self::assertSame(1, $tester->execute(['tool' => 'phpstan']));
        self::assertSame([$root.'/vendor-bin/phpstan/vendor/bin/phpstan', 'analyse', '--no-progress', '--no-ansi', '--', 'src/A.php'], $this->runner->runs[1]['command']);
        self::assertTrue($this->runner->runs[1]['capture']);
        self::assertTrue($this->runner->runs[1]['withErrors']);
        self::assertSame(" [ERROR] Found 1 error\n", $tester->getDisplay());
    }

    public function testPhpStanInColoursOnADecoratedOutput(): void
    {
        $tester = $this->tester($this->package(), 'staged', [new ProcessResult(0, "src/A.php\0")]);

        self::assertSame(0, $tester->execute(['tool' => 'phpstan'], ['decorated' => true]));
        self::assertSame(['--ansi', '--', 'src/A.php'], \array_slice($this->runner->runs[1]['command'], 3));
    }

    public function testPhpStanPassesWhenItExcludesEveryStagedFile(): void
    {
        $tester = $this->tester($this->package(), 'staged', [new ProcessResult(0, "phil-dev.php\0"), new ProcessResult(1, "Note: Using configuration file phpstan.neon.\n\n [ERROR] No files found to analyse.\n")]);

        self::assertSame(0, $tester->execute(['tool' => 'phpstan']));
        self::assertStringContainsString('PHPStan excludes every staged PHP file: nothing to analyse.', $tester->getDisplay());
    }

    public function testThatMessageOnASuccessIsNoSpecialCase(): void
    {
        $tester = $this->tester($this->package(), 'staged', [new ProcessResult(0, "src/A.php\0"), new ProcessResult(0, "No files found to analyse.\n")]);

        self::assertSame(0, $tester->execute(['tool' => 'phpstan']));
        self::assertStringNotContainsString('PHPStan excludes', $tester->getDisplay());
    }

    /**
     * End to end, through git and without a shell: a staged file whose name holds spaces, a semicolon and a
     * command reaches the tool as one argument, and the command it names never runs.
     */
    public function testATrappedFileNameStaysOnePath(): void
    {
        $root = $this->package();
        $this->put($root.'/vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer', "#!/bin/sh\nfor a in \"\$@\"; do printf '%s\\n' \"\$a\"; done > arguments.txt\n");
        chmod($root.'/vendor-bin/php-cs-fixer/vendor/bin/php-cs-fixer', 0o755);
        $trap = 'a b; touch pwned; $(touch pwned2) `touch pwned3`.php';
        $this->put($root.'/'.$trap, '<?php');
        new LocalProcessRunner()->capture(['git', 'init', '--quiet'], $root);
        new LocalProcessRunner()->capture(['git', 'add', '--', $trap], $root);

        $tester = new CommandTester(new Application($root, new LocalProcessRunner())->find('staged'));

        self::assertSame(0, $tester->execute(['tool' => 'php-cs-fixer']));
        self::assertSame("check\n--diff\n--path-mode=intersection\n--\n".$trap."\n", file_get_contents($root.'/arguments.txt'));
        self::assertFileDoesNotExist($root.'/pwned');
        self::assertFileDoesNotExist($root.'/pwned2');
        self::assertFileDoesNotExist($root.'/pwned3');
    }
}
