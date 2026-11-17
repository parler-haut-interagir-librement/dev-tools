<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\Docs\Mirrors;
use Phil\DevTools\Docs\SourceStamp;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Files;
use Phil\DevTools\Process\Executables;
use Phil\DevTools\Split\SecurityCommit;
use Phil\DevTools\Split\SecurityFiles;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\Workspace;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'split', description: 'Splits the finished packages of a monorepo to their public repositories', help: <<<'TXT'
    A member is finished when its phil-dev.php names where it goes, under split.repository. Its history is
    split with splitsh-lite when it is on the PATH, else with git subtree split, into one commit whose tree is
    the member's directory. A member without a SECURITY.md is not split.

    Without split.prefix, a commit on top of the split adds .github/dependabot.yml and the OpenSSF Scorecard
    workflow, and goes to split.repository as its branch: split.branch, else --branch, else main; --tag also
    pushes it as that tag. When the branch already has a tip the split does not descend from, the commit merges
    it, so that the push is a fast-forward. With split.prefix, split.repository is another monorepo, such as the public one: it is cloned
    under var/phil-dev/split/, the split commit is added or merged under the prefix with git subtree, and the
    branch is pushed.

    Nothing is pushed without --push: the command says what it would do. It never forces a push.

    --security-files splits nothing: it adds to each member the SECURITY.md of the skeleton and the security badge
    of its README when they are missing; at the root of a monorepo, the Dependabot configuration, with its cooldown
    and its groups, and the zizmor workflow; and, when the root's split.snyk is true, the Snyk policy and workflow of
    a public monorepo. A file already there is kept. The changes are left to review and commit.
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
            ->addOption('tag', null, InputOption::VALUE_REQUIRED, 'Also push the split commit as this tag, to a split repository')
            ->addOption('security-files', null, InputOption::VALUE_NONE, 'Split nothing: add the missing SECURITY.md and security badge to the members, for review');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->workspace();
        /** @var list<string> $names */
        $names = $input->getArgument('packages');

        if (true === $input->getOption('security-files')) {
            $this->prepare($output, $workspace, $names);

            return self::SUCCESS;
        }

        if (!$workspace->isMonorepo()) {
            $output->writeln('Not a monorepo: nothing to split.');

            return self::SUCCESS;
        }

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

            if (!is_file($package->path('SECURITY.md'))) {
                $output->writeln(\sprintf('%s: no SECURITY.md; a split repository needs its security policy (phil-dev split --security-files adds it).', $package->name));
                $status = self::FAILURE;

                continue;
            }

            $sha = $this->split($package, $splitter);

            if (null === $sha) {
                $output->writeln(\sprintf('%s: the split failed.', $package->name));
                $status = self::FAILURE;

                continue;
            }

            $tag = $input->getOption('tag');
            $ok = null === $prefix
                ? $this->publish($output, $package, $repository, $sha, $branch, \is_string($tag) ? $tag : null, $push)
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

    /**
     * Adds the security files on top of the split, and pushes that commit to the split repository. A dry run builds the
     * commit locally, without reading the remote: the push merges the remote's tip when there is one.
     */
    private function publish(OutputInterface $output, Package $package, string $repository, string $sha, string $branch, ?string $tag, bool $push): bool
    {
        $root = $this->workspace()->root;
        $security = new SecurityCommit($this->runner, $root->dir);
        $tip = $push ? $security->remoteTip($repository, $branch) : '';

        if (null === $tip) {
            $output->writeln(\sprintf('%s: cannot read the branch %s of %s.', $package->name, $branch, $repository));

            return false;
        }

        $head = $security->build($sha, SecurityFiles::forPackage($package->dir, $branch), $tip, $root->path('var/phil-dev/split/security-'.str_replace('/', '-', $package->name)));

        if (null === $head) {
            $output->writeln(\sprintf('%s: the commit of the security files failed.', $package->name));

            return false;
        }

        $refspecs = [$head.':refs/heads/'.$branch, ...(null === $tag ? [] : [$head.':refs/tags/'.$tag])];

        if (!$push) {
            $output->writeln(\sprintf('%s: would push %s, the split %s and its security files, to %s as %s.', $package->name, substr($head, 0, 12), substr($sha, 0, 12), $repository, implode(' and ', array_map(static fn (string $refspec): string => substr($refspec, \strlen($head) + 1), $refspecs))));

            return true;
        }

        return $this->runner->run(['git', 'push', $repository, ...$refspecs], $root->dir)->successful();
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

    /**
     * Adds what the members miss of the security files to their directories: the SECURITY.md of the skeleton, the badge
     * of the README and of its French mirror — restamped when it was up to date —; at the root of a monorepo, its
     * Dependabot configuration and its zizmor workflow; and, when the root's `split.snyk` is true, the Snyk policy and
     * workflow.
     *
     * @param list<string> $names
     */
    private function prepare(OutputInterface $output, Workspace $workspace, array $names): void
    {
        foreach ($workspace->targets($names) as $package) {
            $added = [];

            if (!is_file($package->path('SECURITY.md'))) {
                copy(DevTools::resource('skeleton/common/SECURITY.md'), $package->path('SECURITY.md'));
                $added[] = 'SECURITY.md';
            }

            $repository = SecurityFiles::repositoryOf($package->name, $package->manifest->get('split', 'repository'), $package->manifest->get('split', 'prefix'));

            if (null !== $repository && $this->addBadge($package, SecurityFiles::badge(...$repository))) {
                $added[] = 'the badge of its README';
            }

            $output->writeln(\sprintf('%s: %s.', $package->name, [] === $added ? 'nothing to add' : 'added '.implode(' and ', $added)));
        }

        $files = $workspace->isMonorepo() ? SecurityFiles::MONOREPO : [];

        if ($workspace->root->manifest->flag('split', 'snyk')) {
            $files += ['.snyk' => 'security/monorepo/.snyk', '.github/workflows/snyk.yml' => 'security/monorepo/snyk.yml'];
        }

        foreach ($files as $file => $resource) {
            $target = $workspace->root->path($file);

            if (!is_file($target)) {
                if (!is_dir(\dirname($target))) {
                    mkdir(\dirname($target), recursive: true);
                }

                copy(DevTools::resource($resource), $target);
                $output->writeln(\sprintf('The root: added %s.', $file));
            }
        }

        $output->writeln('Nothing was split or committed: review the changes, then commit them.');
    }

    /**
     * Adds the badge to the README that has none, and to its French mirror.
     */
    private function addBadge(Package $package, string $badge): bool
    {
        $readme = $package->path('README.md');

        if (!is_file($readme)) {
            return false;
        }

        $source = Files::read($readme);

        if (SecurityFiles::hasBadge($source)) {
            return false;
        }

        $updated = SecurityFiles::withBadge($source, $badge);
        file_put_contents($readme, $updated);
        $mirror = $package->path(Mirrors::mirrorOf('README.md'));

        if (is_file($mirror) && !SecurityFiles::hasBadge($french = Files::read($mirror))) {
            // The same line in both: a mirror that was up to date stays so, a stale one stays stale.
            $wasCurrent = SourceStamp::read($french) === SourceStamp::fingerprint($source);
            $french = SecurityFiles::withBadge($french, $badge);
            file_put_contents($mirror, $wasCurrent ? SourceStamp::apply($french, $updated) : $french);
        }

        return true;
    }
}
