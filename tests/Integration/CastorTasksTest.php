<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Integration;

use Ergebnis\PHPUnit\SlowTestDetector\Attribute\MaximumDuration;
use Phil\DevTools\DevTools;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The common tasks of `resources/castor/tasks.php` imported by a project's castor.php and run by Castor itself, with
 * PHPUnit stood in by a script that prints its arguments. Skipped when Castor is not installed.
 */
#[CoversNothing]
final class CastorTasksTest extends TestCase
{
    use TemporaryDirectory;

    private const array TASKS = [
        'ci:all', 'fix:all', 'fix:composer', 'fix:js', 'fix:php', 'fix:rector', 'fix:twig', 'helpers:hooks',
        'helpers:versions', 'lint:all', 'lint:analyse', 'lint:architecture', 'lint:composer', 'lint:container', 'lint:js',
        'lint:php', 'lint:rector', 'lint:twig', 'lint:yaml', 'security:taint', 'test:all', 'test:api', 'test:coverage',
        'test:e2e', 'test:functional', 'test:infection', 'test:integration', 'test:next', 'test:suite', 'test:unit',
    ];

    #[MaximumDuration(10000)]
    public function testAProjectImportsTheTasksAndKeepsItsOwn(): void
    {
        $root = $this->project("['suites' => ['Unit', 'Functional']]");

        $list = $this->castor($root, 'list', '--format=json');

        self::assertSame(0, $list->getExitCode(), $list->getErrorOutput());
        /** @var array{commands: list<array{name: string}>} $commands */
        $commands = json_decode($list->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
        $names = array_column($commands['commands'], 'name');
        self::assertSame([], array_values(array_diff(self::TASKS, $names)), 'Every common task is listed.');
        self::assertContains('own', $names);
    }

    #[MaximumDuration(10000)]
    public function testTheTestsRunTheConfiguredSuitesThenTheProjectsOwnSteps(): void
    {
        $root = $this->project("['suites' => ['Unit', 'Functional'], 'test_also' => ['own']]");

        $test = $this->castor($root, 'test', '--filter=a b');

        self::assertStringContainsString("phpunit --testsuite=Unit,Functional --filter=a b --stop-on-failure\n", $this->printed($test));
        self::assertStringContainsString('own ran', $this->printed($test));
        self::assertSame(1, $test->getExitCode(), 'The own step returned 3: the task fails.');

        $unit = $this->castor($root, 'test:suite', 'Api');

        self::assertSame(0, $unit->getExitCode(), $unit->getErrorOutput());
        self::assertStringContainsString("phpunit --testsuite=Api\n", $this->printed($unit));
    }

    #[MaximumDuration(10000)]
    public function testTheCoverageMustReachItsThreshold(): void
    {
        $failing = $this->castor($this->project('[]'), 'coverage');

        self::assertSame(1, $failing->getExitCode());
        self::assertStringContainsString('87.50 % of the lines are covered, under the threshold of 100 %.', $this->printed($failing));

        $passing = $this->castor($this->project("['coverage_threshold' => 85]"), 'coverage');

        self::assertSame(0, $passing->getExitCode(), $this->printed($passing));
        self::assertStringContainsString('87.50 % of the lines are covered; the threshold is 85 %.', $this->printed($passing));
    }

    #[MaximumDuration(10000)]
    public function testWhatTheProjectLacksIsSkipped(): void
    {
        $root = $this->project("['yaml' => ['config']]");

        $yaml = $this->castor($root, 'lint:yaml');
        $twig = $this->castor($root, 'lint:twig');

        self::assertSame(0, $yaml->getExitCode());
        self::assertStringContainsString('No YAML file or directory to lint.', $this->printed($yaml));
        self::assertSame(0, $twig->getExitCode());
        self::assertStringContainsString('No "templates" directory: no Twig template to lint.', $this->printed($twig));
    }

    #[MaximumDuration(10000)]
    public function testUnknownSettingsAreRefused(): void
    {
        $test = $this->castor($this->project("['suite' => ['Unit']]"), 'test');

        self::assertNotSame(0, $test->getExitCode());
        self::assertStringContainsString('Unknown key(s) in PHIL_CASTOR: "suite"', $this->printed($test));
    }

    private function project(string $settings): string
    {
        $root = $this->temporaryDirectory();
        $tasks = var_export(DevTools::directory().'/resources/castor/tasks.php', true);
        $this->put($root.'/castor.php', <<<PHP
            <?php

            use Castor\\Attribute\\AsTask;

            use function Castor\\import;

            defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);
            const PHIL_CASTOR = {$settings};
            import({$tasks});

            #[AsTask]
            function own(): int
            {
                echo "own ran\\n";

                return 3;
            }
            PHP);
        // The stand-in of PHPUnit prints its arguments, and writes a summary where a coverage report is asked for.
        $this->put($root.'/vendor/bin/phpunit', <<<'PHP'
            #!/usr/bin/env php
            <?php

            echo 'phpunit ', implode(' ', array_slice($argv, 1)), "\n";

            foreach ($argv as $argument) {
                if (str_starts_with($argument, '--coverage-text=')) {
                    $report = substr($argument, 16);
                    is_dir(dirname($report)) || mkdir(dirname($report), 0o777, true);
                    file_put_contents($report, " Summary:\n  Lines:   87.50% (7/8)\n");
                }
            }
            PHP);
        chmod($root.'/vendor/bin/phpunit', 0o755);

        return $root;
    }

    private function castor(string $root, string ...$arguments): Process
    {
        $castor = new ExecutableFinder()->find('castor');

        if (null === $castor) {
            self::markTestSkipped('Castor is not installed.');
        }

        $process = new Process([$castor, '--no-interaction', ...$arguments], $root, ['CASTOR_NO_REMOTE' => '1', 'COLUMNS' => '200']);
        $process->setTimeout(60);
        $process->run();

        return $process;
    }

    /**
     * What Castor printed, its terminal line ends made plain.
     */
    private function printed(Process $process): string
    {
        return str_replace("\r\n", "\n", $process->getOutput().$process->getErrorOutput());
    }
}
