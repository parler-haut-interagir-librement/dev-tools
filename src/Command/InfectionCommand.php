<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\InfectionConfig;
use Phil\DevTools\Config\PhpUnitConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'infection', description: 'Runs mutation testing on the whole code, or on the files changed since a base ref', help: <<<'TXT'
    Generates var/phil-dev/infection.json from the composer.json and phil-dev.php of each package — the
    source directories, their excludes and the named exceptions of each package — then runs Infection on
    the Unit suite, at 100 % of mutants killed and of covered mutants killed.

    Full: every source directory, once a week and before each release.
    --changed: the PHP files added or modified since the base ref, committed or not, once per batch before its
    commit; nothing to mutate passes.

    While the planned packages are skipped (--skip-planned, PHIL_DEV_SKIP_PLANNED=1), their code is not mutated
    and their tests do not run: Infection reads var/phil-dev/phpunit/phpunit.xml, the root's configuration
    without them.
    TXT)]
final class InfectionCommand extends WorkspaceCommand
{
    public const string BASE_VARIABLE = 'PHIL_DEV_BASE_REF';

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('changed', null, InputOption::VALUE_OPTIONAL, 'Mutate only the files added or modified since this ref: PHIL_DEV_BASE_REF, else origin/main, else main', false)
            ->addArgument('arguments', InputArgument::IS_ARRAY, 'More arguments for Infection, after --');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        $phpUnit = new PhpUnitConfig($workspace)->withoutSkipped();
        $phpUnitDirectory = null === $phpUnit ? null : \dirname($this->generate('phpunit/phpunit.xml', $phpUnit));
        $config = $this->generate('infection.json', new InfectionConfig($workspace, $phpUnitDirectory)->json());
        $command = [
            $this->tool('infection', 'infection'),
            '--configuration='.$config,
            '--no-progress',
            '--show-mutations',
            '--threads=max',
        ];

        $changed = $input->getOption('changed');

        if (false !== $changed) {
            $base = \is_string($changed) && '' !== $changed ? $changed : $this->defaultBase();
            $files = $this->changedFiles($base);

            if ([] === $files) {
                $output->writeln(\sprintf('No source file changed since %s: nothing to mutate.', $base));

                return self::SUCCESS;
            }

            $output->writeln(\sprintf('Mutating the %d source files changed since %s.', \count($files), $base));
            $command[] = '--filter='.implode(',', $files);
        }

        /** @var list<string> $arguments */
        $arguments = $input->getArgument('arguments');

        return $this->runner->run([...$command, ...$arguments], $this->workspace()->root->dir, $this->testEnvironment())->exitCode;
    }

    /**
     * The source files added or modified since the point where the branch left `$base`, committed or not, relative to
     * the root. Infection's own `--git-diff-filter` is not used: it resolves the files against the directory of the
     * configuration, which is generated under `var/phil-dev/`.
     *
     * @return list<string>
     */
    private function changedFiles(string $base): array
    {
        $root = $this->workspace()->root->dir;
        $directories = [];

        foreach ($this->workspace()->analysed() as $package) {
            array_push($directories, ...$package->sourceDirectories());
        }

        $mergeBase = trim($this->runner->capture(['git', 'merge-base', $base, 'HEAD'], $root)->output);
        $diff = $this->runner->capture(['git', 'diff', '--name-only', '--diff-filter=AM', '' === $mergeBase ? $base : $mergeBase, '--', ...$directories], $root);

        $files = [];

        foreach (explode("\n", $diff->output) as $file) {
            if (str_ends_with($file, '.php')) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function defaultBase(): string
    {
        $variable = getenv(self::BASE_VARIABLE);

        if (\is_string($variable) && '' !== $variable) {
            return $variable;
        }

        $remote = $this->runner->capture(['git', 'rev-parse', '--verify', '--quiet', 'origin/main'], $this->workspace()->root->dir);

        return $remote->successful() ? 'origin/main' : 'main';
    }
}
