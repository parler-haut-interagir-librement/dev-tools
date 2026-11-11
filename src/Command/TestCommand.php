<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\PhpUnitConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'test', description: 'Runs the tests with ParaTest, or with PHPUnit when ParaTest is not installed', help: <<<'TXT'
    Runs the test suites of phpunit.dist.xml in parallel with ParaTest, one process per core, when
    vendor/bin/paratest exists; else with PHPUnit. The root's phpunit.parallel: false keeps PHPUnit, for
    tests that share a resource they cannot share at once. Infection always replays the Unit suite with PHPUnit.

    The tests run with TMPDIR under var/tmp of the root, unless it already points inside the root: the test
    kernels that keep their cache under the system's temporary directory get one per checkout.

    While the planned packages are skipped (--skip-planned, PHIL_DEV_SKIP_PLANNED=1), the tests run with
    var/phil-dev/phpunit/phpunit.xml: the root's configuration without them.
    TXT)]
final class TestCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('suite', null, InputOption::VALUE_REQUIRED, 'Only this test suite, such as Unit or Integration')
            ->addArgument('arguments', InputArgument::IS_ARRAY, 'More arguments for ParaTest or PHPUnit, after --');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        $root = $workspace->root;
        $paratest = $root->path('vendor/bin/paratest');
        $parallel = is_file($paratest) && $root->manifest->flag('phpunit', 'parallel', true);
        $command = [$parallel ? $paratest : $root->path('vendor/bin/phpunit')];
        $configuration = new PhpUnitConfig($workspace)->withoutSkipped();

        if (null !== $configuration) {
            $command[] = '--configuration='.$this->generate('phpunit/phpunit.xml', $configuration);
        }

        $suite = $input->getOption('suite');

        if (\is_string($suite) && '' !== $suite) {
            $command[] = '--testsuite='.$suite;
        }

        /** @var list<string> $arguments */
        $arguments = $input->getArgument('arguments');

        return $this->runner->run([...$command, ...$arguments], $root->dir, $this->testEnvironment())->exitCode;
    }
}
