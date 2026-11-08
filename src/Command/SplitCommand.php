<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Files;
use Phil\DevTools\Process\Executables;
use Phil\DevTools\Workspace\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'split', description: 'Splits the finished packages of a monorepo to their public repositories', help: <<<'TXT'
    A member is finished when its phil-dev.php names where it goes, under split.repository. Its history is
    split with splitsh-lite when it is on the PATH, else with git subtree split, into one commit whose tree is
    the member's directory.

    Without split.prefix, that commit goes to split.repository as its branch: split.branch, else --branch, else
    main; --tag also pushes it as that tag. With split.prefix, split.repository is another monorepo, such as the
    public one: it is cloned under var/phil-dev/split/, the commit is added or merged under the prefix with git
    subtree, and the branch is pushed.

    Nothing is pushed without --push: the command says what it would do. It never forces a push.
    TXT)]
final class SplitCommand extends WorkspaceCommand
{
    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('packages', InputArgument::IS_ARRAY, 'The packages to split, by directory or Composer name; every finished one by default')
            ->addOption('push', null, InputOption::VALUE_NONE, 'Push; without it, only say what would be pushed')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'The branch of the target, when the manifest names none', 'main')
            ->addOption('tag', null, InputOption::VALUE_REQUIRED, 'Also push the split commit as this tag, to a split repository');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();

        if (!$workspace->isMonorepo()) {
            $output->writeln('Not a monorepo: nothing to split.');

            return self::SUCCESS;
        }

        /** @var list<string> $names */
        $names = $input->getArgument('packages');
        $push = true === $input->getOption('push');
        $splitter = Executables::fromEnvironment()->find('splitsh-lite');
        $status = self::SUCCESS;

        foreach ($workspace->targets($names) as $package) {
            $repository = $package->manifest->get('split', 'repository');

            if (null === $repository) {
                $output->writeln(\sprintf('%s: not finished, no split.repository.', $package->name));

                continue;
            }

            [$branch, $prefix] = $this->target($package, $input);

            if (!\is_string($repository) || '' === $repository) {
                throw new InvalidManifest(\sprintf('split.repository of %s must be the URL of a git repository.', $package->name));
            }

            $sha = $this->split($package, $splitter);

            if (null === $sha) {
                $output->writeln(\sprintf('%s: the split failed.', $package->name));
                $status = self::FAILURE;

                continue;
            }

            $tag = $input->getOption('tag');
            $ok = null === $prefix
                ? $this->pushBranch($output, $package, $repository, $sha, $branch, \is_string($tag) ? $tag : null, $push)
                : $this->mergeInto($output, $package, $repository, $sha, $branch, $prefix, $push);

            if (!$ok) {
                $status = self::FAILURE;
            }
        }

        if (!$push) {
            $output->writeln('Nothing was pushed: run again with --push.');
        }

        return $status;
    }

    /**
     * @return array{string, string|null} the branch, and the prefix in a target monorepo
     */
    private function target(Package $package, InputInterface $input): array
    {
        $branch = $package->manifest->get('split', 'branch') ?? $input->getOption('branch');
        $prefix = $package->manifest->get('split', 'prefix');

        if (!\is_string($branch) || '' === $branch || (null !== $prefix && (!\is_string($prefix) || '' === trim($prefix, '/')))) {
            throw new InvalidManifest(\sprintf('split.branch and split.prefix of %s must be a branch and a directory.', $package->name));
        }

        return [$branch, null === $prefix ? null : trim($prefix, '/')];
    }

    private function split(Package $package, ?string $splitter): ?string
    {
        $root = $this->workspace()->root->dir;
        $result = null === $splitter
            ? $this->runner->capture(['git', 'subtree', 'split', '--prefix='.$package->relative, 'HEAD'], $root)
            : $this->runner->capture([$splitter, '--prefix='.$package->relative, '--origin=HEAD'], $root);
        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $result->output)), static fn (string $line): bool => '' !== $line));
        $sha = [] === $lines ? '' : $lines[\count($lines) - 1];

        return $result->successful() && 1 === preg_match('/^[0-9a-f]{40}$/', $sha) ? $sha : null;
    }

    private function pushBranch(OutputInterface $output, Package $package, string $repository, string $sha, string $branch, ?string $tag, bool $push): bool
    {
        $refspecs = [$sha.':refs/heads/'.$branch, ...(null === $tag ? [] : [$sha.':refs/tags/'.$tag])];

        if (!$push) {
            $output->writeln(\sprintf('%s: would push %s to %s as %s.', $package->name, substr($sha, 0, 12), $repository, implode(' and ', array_map(static fn (string $refspec): string => substr($refspec, 41), $refspecs))));

            return true;
        }

        return $this->runner->run(['git', 'push', $repository, ...$refspecs], $this->workspace()->root->dir)->successful();
    }

    private function mergeInto(OutputInterface $output, Package $package, string $repository, string $sha, string $branch, string $prefix, bool $push): bool
    {
        if (!$push) {
            $output->writeln(\sprintf('%s: would merge %s under %s/ of %s, branch %s.', $package->name, substr($sha, 0, 12), $prefix, $repository, $branch));

            return true;
        }

        $root = $this->workspace()->root;
        $slug = str_replace('/', '-', $package->name);
        $work = $root->path('var/phil-dev/split/'.$slug);
        $local = 'phil-dev-split/'.$slug;

        if (is_dir($work)) {
            Files::removeDirectory($work);
        }

        $prepared = $this->runner->run(['git', 'clone', '--branch', $branch, '--single-branch', $repository, $work], $root->dir)->successful()
            && $this->runner->run(['git', 'branch', '--force', $local, $sha], $root->dir)->successful();

        if (!$prepared) {
            return false;
        }

        // The first split adds the directory; the next ones merge into what the target already has.
        $mode = is_dir($work.'/'.$prefix) ? 'pull' : 'add';

        return $this->runner->run(['git', 'subtree', $mode, '--prefix='.$prefix, '--message=Split of '.$package->name.' at '.substr($sha, 0, 12), $root->dir, $local], $work)->successful()
            && $this->runner->run(['git', 'push', 'origin', $branch], $work)->successful();
    }
}
