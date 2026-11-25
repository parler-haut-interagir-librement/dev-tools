<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Config\InfectionConfig;
use Phil\DevTools\Config\PhpUnitConfig;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\Workspace;
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
    ones git does not ignore, once per batch before its commit; nothing to mutate passes, and so do changed files
    without a mutant. Infection receives each file as an absolute path of its own, after the other arguments, and
    only the files inside the source directories it mutates. Without a ref, the base is PHIL_DEV_BASE_REF, else
    whichever of origin/main and main the branch left last, else main.

    The changed files narrow the run to the packages that hold them, as PHIL_DEV_PACKAGES would: only their tests
    run, to collect the coverage and against each mutant, and only their code is covered. Infection and its initial
    test run get the memory limit of the root's infection.memoryLimit, 4G by default.

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
        $changedOnly = [];
        $paths = [];
        $changed = $input->getOption('changed');

        if (false !== $changed) {
            [$base, $since] = \is_string($changed) && '' !== $changed ? [$changed, $this->mergeBase($changed)] : $this->defaultBase();
            $files = $this->changedFiles($workspace, $since);

            if (null === $files) {
                $output->writeln(\sprintf('<error>git could not list the files changed since %s in %s: nothing was mutated.</error>', $base, $workspace->root->dir));

                return self::FAILURE;
            }

            if ([] === $files) {
                $output->writeln(\sprintf('No source file changed since %s: nothing to mutate.', $base));

                return self::SUCCESS;
            }

            $workspace = $this->owners($workspace, $files);
            $paths = $this->mutable($files, $workspace);

            if ([] === $paths) {
                $output->writeln(\sprintf('No file changed since %s is in a source directory Infection mutates: nothing to mutate.', $base));

                return self::SUCCESS;
            }

            $output->writeln(\sprintf('Mutating the %d source files changed since %s.', \count($paths), $base));

            if ($workspace->isMonorepo()) {
                $output->writeln('With the tests of '.implode(', ', array_map(static fn (Package $package): string => $package->name, $workspace->analysed())).'.');
            }

            // A change without a mutant passes.
            $changedOnly[] = '--ignore-msi-with-no-mutations';
        }

        $phpUnit = new PhpUnitConfig($workspace, narrowed: true)->generated();
        $phpUnitDirectory = null === $phpUnit ? null : \dirname($this->generate('phpunit/phpunit.xml', $phpUnit));
        $infection = new InfectionConfig($workspace, $phpUnitDirectory);
        $config = $this->generate('infection.json', $infection->json());

        /** @var list<string> $arguments */
        $arguments = $input->getArgument('arguments');

        return $this->runner->run([
            \PHP_BINARY,
            '-d',
            'memory_limit='.$infection->memoryLimit(),
            $this->tool('infection', 'infection'),
            '--configuration='.$config,
            '--no-progress',
            '--show-mutations',
            '--threads=max',
            ...$changedOnly,
            ...$arguments,
            // After --: an option whose value is optional, such as --ignore-msi-with-no-mutations, takes the next
            // argument that is not an option, so it would take the first path and Infection would mutate everything.
            ...([] === $paths ? [] : ['--', ...$paths]),
        ], $workspace->root->dir, $this->testEnvironment())->exitCode;
    }

    /**
     * The workspace narrowed to the packages that hold the changed files, so that only their tests run, first to
     * collect the coverage and then against each mutant: a file belongs to the member whose directory holds it, else
     * to the root. A package on its own stays as it is.
     *
     * @param non-empty-list<string> $files relative to the root
     */
    private function owners(Workspace $workspace, array $files): Workspace
    {
        if (!$workspace->isMonorepo()) {
            return $workspace;
        }

        $names = [];

        foreach ($files as $file) {
            $owner = $workspace->root;

            foreach ($workspace->members as $member) {
                if (str_starts_with($workspace->root->path($file), $member->dir.'/')) {
                    $owner = $member;
                }
            }

            $names[$owner->name] = $owner->isRoot() ? $owner->name : $owner->relative;
        }

        return Workspace::load($workspace->root->dir, array_values($names));
    }

    /**
     * The changed files Infection mutates, absolute, each one an argument of its own: Infection reads a positional
     * argument inside a source directory of its configuration as a file to mutate, any other existing path as a test
     * path for PHPUnit, and refuses one that does not exist; it resolves a relative one against the directory of the
     * configuration, under `var/phil-dev/`. A file outside the source directories of the narrowed workspace — a package
     * whose `infection.directories` leaves it out, or a planned package that is skipped — is not passed.
     *
     * @param non-empty-list<string> $files relative to the root
     *
     * @return list<string>
     */
    private function mutable(array $files, Workspace $workspace): array
    {
        $directories = new InfectionConfig($workspace)->directories();
        $paths = [];

        foreach ($files as $file) {
            $path = $workspace->root->path($file);

            if (array_any($directories, static fn (string $directory): bool => str_starts_with($path, rtrim($directory, '/').'/'))) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * The source files added, modified or renamed since `$since`, committed or not, and the untracked ones that git
     * does not ignore, relative to the root and sorted: the same files whatever was staged, committed or pushed in the
     * meantime. Infection's own `--git-diff-filter` is not used: it resolves the files against the directory of the
     * configuration, which is generated under `var/phil-dev/`. Null when git fails — a ref it does not know, or a
     * worktree whose repository it cannot reach, as in a container that mounts the worktree alone —, so that a failure
     * never passes for a change with nothing to mutate.
     *
     * @return list<string>|null
     */
    private function changedFiles(Workspace $workspace, string $since): ?array
    {
        $root = $workspace->root->dir;
        $directories = [];

        foreach ($workspace->analysed() as $package) {
            array_push($directories, ...$package->sourceDirectories());
        }

        $diff = $this->runner->capture(['git', 'diff', '--name-only', '--diff-filter=AMR', $since, '--', ...$directories], $root);
        $untracked = $this->runner->capture(['git', 'ls-files', '--others', '--exclude-standard', '--', ...$directories], $root);

        if (!$diff->successful() || !$untracked->successful()) {
            return null;
        }

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
