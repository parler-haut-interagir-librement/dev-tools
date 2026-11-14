<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\Workspace\TestKernels;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'lint-apps', description: 'Lints the container, the expressions and the YAML of each functional test application', help: <<<'TXT'
    Finds the kernels under tests/Functional/ of each package — the concrete classes whose parent's name ends
    with Kernel — and runs, through each application's own console, in a process of its own:

      lint:container            the services, their arguments and their types, as the container compiles them;
      lint:expressions          the expressions of controller attributes and those the configuration lists, such
                                as allow_if of access_control (Symfony 8.2);
      lint:yaml --check-schema  the YAML files of the application's config/ directory, against the JSON schema
                                of its configuration; the syntax only when opis/json-schema is not installed.

    A command the application does not have is skipped with a note, and so is a kernel whose constructor needs
    more than an environment and a debug flag. A package leaves a kernel out by its class name under apps.skip
    of its phil-dev.php. The kernels boot in the test environment with debug on, with TMPDIR under var/tmp/ of
    the root as for the tests.
    TXT)]
final class LintAppsCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this->addArgument('packages', InputArgument::IS_ARRAY, 'The packages, by directory or Composer name; all by default');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->gateIsOn('apps', $output)) {
            return self::SUCCESS;
        }

        $workspace = $this->workspace();
        $root = $workspace->root;
        /** @var list<string> $names */
        $names = $input->getArgument('packages');
        $autoload = $root->path('vendor/autoload.php');
        $status = self::SUCCESS;
        $linted = false;

        foreach ($workspace->targets($names) as $package) {
            $skipped = $package->manifest->strings('apps', 'skip');

            foreach (TestKernels::of($package) as $class => $file) {
                $output->writeln(\sprintf('== %s: %s', $package->name, substr($file, \strlen($root->dir) + 1)));
                $linted = true;

                if (\in_array($class, $skipped, true)) {
                    $output->writeln('Left out by apps.skip.');

                    continue;
                }

                if (!$this->runner->run([\PHP_BINARY, DevTools::resource('lint/app.php'), $autoload, $class], $root->dir, $this->testEnvironment())->successful()) {
                    $status = self::FAILURE;
                }
            }
        }

        if (!$linted) {
            $output->writeln('No package has a functional test application: nothing to lint.');
        }

        return $status;
    }
}
