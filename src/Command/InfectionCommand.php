<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\InfectionConfig;
use Phil\DevTools\Config\PhpUnitConfig;
use Phil\DevTools\Workspace\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'infection', description: 'Runs mutation testing on the whole code, or on the files changed since a base ref', help: <<<'TXT'
    Generates var/phil-dev/infection.json from the composer.json and phil-dev.php of each package — the
    source directories, their excludes and the named exceptions of each package — then runs Infection on
    the Unit suite, at 100 % of mutants killed and of covered mutants killed. The timeout of a mutant is the
    longest infection.timeout of the packages analysed, else Infection's 10 seconds.

    Full: every source directory, once a week and before each release.
    --changed: the PHP files added, modified or renamed since the base ref, committed or not, and the untracked
    ones git does not ignore, once per batch before its commit; nothing to mutate passes. Without a ref, the base is
    PHIL_DEV_BASE_REF, else whichever of origin/main and main the branch left last, else main.

    Run from the directory of a member of a monorepo that has no Infection of its own under vendor-bin/, it
    runs from the monorepo's root on that member alone, as PHIL_DEV_PACKAGES would narrow it.

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
            ->addOption('changed', null, InputOption::VALUE_OPTIONAL, 'Mutate only the files added, modified or renamed since this ref: PHIL_DEV_BASE_REF, else origin/main or main, whichever the branch left last', false)
            ->addArgument('arguments', InputArgument::IS_ARRAY, 'More arguments for Infection, after --');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!is_file($this->tool('infection', 'infection')) && ($member = $this->enterEnclosingMonorepo()) instanceof Package) {
            $output->writeln(\sprintf('Running from the monorepo at %s, on %s alone.', $this->root(), $member->name));
        }

        $workspace = $this->workspace();
        $phpUnit = new PhpUnitConfig($workspace)->generated();
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
            [$base, $since] = \is_string($changed) && '' !== $changed ? [$changed, $this->mergeBase($changed)] : $this->defaultBase();
            $files = $this->changedFiles($since);

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
     * The source files added, modified or renamed since `$since`, committed or not, and the untracked ones that git
     * does not ignore, relative to the root and sorted: the same files whatever was staged, committed or pushed in the
     * meantime. Infection's own `--git-diff-filter` is not used: it resolves the files against the directory of the
     * configuration, which is generated under `var/phil-dev/`.
     *
     * @return list<string>
     */
    private function changedFiles(string $since): array
    {
        $root = $this->workspace()->root->dir;
        $directories = [];

        foreach ($this->workspace()->analysed() as $package) {
            array_push($directories, ...$package->sourceDirectories());
        }

        $diff = $this->runner->capture(['git', 'diff', '--name-only', '--diff-filter=AMR', $since, '--', ...$directories], $root);
        $untracked = $this->runner->capture(['git', 'ls-files', '--others', '--exclude-standard', '--', ...$directories], $root);

        $files = [];

        foreach (explode("\n", $diff->output."\n".$untracked->output) as $file) {
            if (str_ends_with($file, '.php')) {
                $files[$file] = $file;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * The commit where the branch left `$ref`, or `$ref` itself when git finds none.
     */
    private function mergeBase(string $ref): string
    {
        $mergeBase = trim($this->runner->capture(['git', 'merge-base', $ref, 'HEAD'], $this->workspace()->root->dir)->output);

        return '' === $mergeBase ? $ref : $mergeBase;
    }

    /**
     * The base ref and the commit to compare with: PHIL_DEV_BASE_REF; else, of `origin/main` and `main`, the one the
     * branch left last, so that the merges of the local `main` not pushed yet do not count as changes of the branch;
     * else `main`.
     *
     * @return array{string, string}
     */
    private function defaultBase(): array
    {
        $variable = getenv(self::BASE_VARIABLE);

        if (\is_string($variable) && '' !== $variable) {
            return [$variable, $this->mergeBase($variable)];
        }

        $root = $this->workspace()->root->dir;
        $chosen = null;

        foreach (['origin/main', 'main'] as $ref) {
            if (!$this->runner->capture(['git', 'rev-parse', '--verify', '--quiet', $ref], $root)->successful()) {
                continue;
            }

            $candidate = [$ref, $this->mergeBase($ref)];

            if (null === $chosen || $this->runner->capture(['git', 'merge-base', '--is-ancestor', $chosen[1], $candidate[1]], $root)->successful()) {
                $chosen = $candidate;
            }
        }

        return $chosen ?? ['main', 'main'];
    }
}
