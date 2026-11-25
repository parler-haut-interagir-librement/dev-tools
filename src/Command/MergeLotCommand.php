<?php

declare(strict_types=1);

namespace Phil\DevTools\Command;

use Override;
use Phil\DevTools\Exception\MergeLockHeld;
use Phil\DevTools\Files;
use Phil\DevTools\Merge\LockHolder;
use Phil\DevTools\Merge\LockTakeover;
use Phil\DevTools\Merge\MergeFailure;
use Phil\DevTools\Merge\MergeLock;
use Phil\DevTools\Merge\MergeMessage;
use Phil\DevTools\Merge\Worktrees;
use Phil\DevTools\Process\Executables;
use Phil\DevTools\Process\ProcessResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'merge-lot', description: 'Merges the branch of a lot into main, from its rebase to the removal of its worktree, stopping at the first failure', help: <<<'TXT'
    Runs from the checkout of main of a repository, PHIL_DEV_ROOT or the working directory, and merges the
    branch, checked out in a worktree of its own, step by step:

      1. takes the lock .git/fusion-lot.lock, which holds the PID, the start time, the lot and the branch;
      2. checks that no tracked file is modified, in the checkout of main and in the worktree;
      3. rebases the branch onto main in its worktree, then runs composer install there when a composer.lock
         changed;
      4. runs the gates in the worktree: each script of the gates script of its composer.json but Infection,
         the full PHPStan among them;
      5. merges with git merge --no-ff, the message « Fusion du lot <lot> » and one bullet per commit;
      6. checks the merge commit: its parents are main before the merge and the tip of the branch, its subject
         is the one above;
      7. runs composer install in the checkout of main when the merge changed a composer.lock;
      8. removes the worktree with git worktree remove, then the branch with git branch -d.

    The first step that fails stops the merge, with its exit code: 2 nothing to merge, 3 locked, 4 tracked files
    modified, 5 rebase, 6 composer install, 7 gate, 8 merge, 9 merge commit, 10 cleanup. A failed rebase or
    merge is aborted; nothing is removed, and the lock is released.

    A lock is taken over, with a message, when its process is gone or when it has not moved for 30 minutes; the
    merge touches it at each step. A merge of the same lot that stopped is then run again from the start: its
    unfinished git merge is aborted first. A branch already merged by its merge commit only has its worktree and
    branch removed.

    The lot is the branch without its b/ prefix, unless --lot names it.

        phil-dev merge-lot b/r72-merge-lot
    TXT)]
final class MergeLotCommand extends WorkspaceCommand
{
    private const string LOCK = 'fusion-lot.lock';

    /**
     * The lock files whose change calls for `composer install`.
     */
    private const array LOCK_FILES = ['composer.lock', 'vendor-bin/*/composer.lock'];

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('branch', InputArgument::REQUIRED, 'The branch of the lot, checked out in its worktree')
            ->addOption('main', null, InputOption::VALUE_REQUIRED, 'The branch it is merged into', 'main')
            ->addOption('lot', null, InputOption::VALUE_REQUIRED, 'The lot named in the merge commit; the branch without b/ by default');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $branch = $input->getArgument('branch');
        $main = $input->getOption('main');
        $lotOption = $input->getOption('lot');
        $lot = \is_string($lotOption) ? $lotOption : MergeMessage::lotOf(\is_string($branch) ? $branch : '');

        if (!\is_string($branch) || !\is_string($main) || !$this->isBranchName($branch) || !$this->isBranchName($main) || 1 !== preg_match('/^[^\x00-\x1f\x7f]+$/', $lot)) {
            return $this->fail($output, MergeFailure::Usage, 'The branch, the main branch and the lot must be plain names.');
        }

        $top = $this->git(['rev-parse', '--show-toplevel'], $this->root());
        $common = $this->git(['rev-parse', '--path-format=absolute', '--git-common-dir'], $this->root());

        if (!$top->successful() || !$common->successful()) {
            return $this->fail($output, MergeFailure::Usage, \sprintf('%s is not in a git repository.', $this->root()));
        }

        $pid = (int) getmypid();
        $lock = new MergeLock(trim($common->output).'/'.self::LOCK, $this->runner, Executables::fromEnvironment()->find('ps'));

        try {
            $takeover = $lock->acquire(new LockHolder($pid, $lot, $branch, date(\DATE_ATOM)), time());
        } catch (MergeLockHeld $mergeLockHeld) {
            return $this->fail($output, MergeFailure::Locked, $mergeLockHeld->getMessage());
        }

        try {
            if ($takeover instanceof LockTakeover) {
                $output->writeln($takeover->describe());
                $output->writeln($takeover->previous->lot === $lot
                    ? 'Its merge is run again.'
                    : \sprintf('The merge of lot %s did not finish: run phil-dev merge-lot %s again after this one.', $takeover->previous->lot, $takeover->previous->branch));
            }

            $failure = $this->merge($output, $lock, trim($top->output), $branch, $main, $lot, $takeover);
        } finally {
            $lock->release($pid);
        }

        return $failure->value ?? self::SUCCESS;
    }

    /**
     * Every step after the lock; null once the lot is merged and its worktree and branch removed.
     */
    private function merge(OutputInterface $output, MergeLock $lock, string $top, string $branch, string $main, string $lot, ?LockTakeover $takeover): ?MergeFailure
    {
        $current = $this->git(['symbolic-ref', '--quiet', '--short', 'HEAD'], $top);

        if (trim($current->output) !== $main) {
            return $this->stop($output, MergeFailure::Usage, \sprintf('%s is not on %s: merge-lot runs from the checkout of %s.', $top, $main, $main));
        }

        $tip = $this->revision('refs/heads/'.$branch, $top);

        if (null === $tip) {
            return $this->stop($output, MergeFailure::Usage, \sprintf('No branch %s.', $branch));
        }

        $failure = $this->unfinishedMerge($output, $top, $tip, $lot, $takeover) ?? $this->clean($output, $top);

        if ($failure instanceof MergeFailure) {
            return $failure;
        }

        $worktree = Worktrees::parse($this->git(['worktree', 'list', '--porcelain', '-z'], $top)->output)[$branch] ?? null;

        if ($this->git(['merge-base', '--is-ancestor', $tip, 'HEAD'], $top)->successful()) {
            return $this->alreadyMerged($output, $lock, $top, $branch, $main, $lot, $tip, $worktree);
        }

        if (null === $worktree || $worktree === $top) {
            return $this->stop($output, MergeFailure::Usage, \sprintf('No worktree of its own has %s checked out.', $branch));
        }

        $failure = $this->clean($output, $worktree) ?? $this->rebase($output, $lock, $worktree, $main) ?? $this->gates($output, $lock, $worktree);

        if ($failure instanceof MergeFailure) {
            return $failure;
        }

        $lock->touch();
        $before = (string) $this->revision('HEAD', $top);
        $tip = (string) $this->revision('refs/heads/'.$branch, $top);
        $subjects = explode("\n", $this->git(['log', '--reverse', '--format=%s', $before.'..'.$tip], $top)->output);
        $output->writeln(\sprintf('Merging %s into %s.', $branch, $main));

        if (!$this->runner->run(['git', 'merge', '--no-ff', '--no-edit', '-m', MergeMessage::build($lot, $subjects), $tip], $top)->successful()) {
            $this->runner->run(['git', 'merge', '--abort'], $top);

            return $this->stop($output, MergeFailure::Merge, 'git merge failed; it was aborted.');
        }

        if (!$this->isMergeCommit($top, 'HEAD', $before, $tip, $lot)) {
            return $this->stop($output, MergeFailure::Verify, \sprintf('HEAD of %s is not the merge commit of %s on %s.', $main, $tip, $before));
        }

        return $this->install($output, $lock, $top, $before, 'HEAD') ?? $this->cleanup($output, $lock, $top, $branch, $worktree);
    }

    /**
     * A git merge left unfinished in the checkout of main: aborted when the merge of the same lot stopped and its lock
     * was taken over, else a failure.
     */
    private function unfinishedMerge(OutputInterface $output, string $top, string $tip, string $lot, ?LockTakeover $takeover): ?MergeFailure
    {
        $merging = $this->revision('MERGE_HEAD', $top);

        if (null === $merging) {
            return null;
        }

        if (!$takeover instanceof LockTakeover || $takeover->previous->lot !== $lot || $merging !== $tip) {
            return $this->stop($output, MergeFailure::Dirty, \sprintf('A git merge is in progress in %s.', $top));
        }

        $output->writeln('Aborting the unfinished merge of the lot.');

        return $this->runner->run(['git', 'merge', '--abort'], $top)->successful() ? null : $this->stop($output, MergeFailure::Dirty, 'git merge --abort failed.');
    }

    private function clean(OutputInterface $output, string $directory): ?MergeFailure
    {
        $status = $this->git(['status', '--porcelain', '--untracked-files=no'], $directory);

        if (!$status->successful() || '' !== trim($status->output)) {
            return $this->stop($output, MergeFailure::Dirty, \sprintf('Tracked files are modified in %s.', $directory));
        }

        foreach (['rebase-merge', 'rebase-apply'] as $state) {
            $path = trim($this->git(['rev-parse', '--path-format=absolute', '--git-path', $state], $directory)->output);

            if ('' !== $path && is_dir($path)) {
                return $this->stop($output, MergeFailure::Dirty, \sprintf('A rebase is in progress in %s.', $directory));
            }
        }

        return null;
    }

    private function rebase(OutputInterface $output, MergeLock $lock, string $worktree, string $main): ?MergeFailure
    {
        $lock->touch();
        $before = (string) $this->revision('HEAD', $worktree);
        $output->writeln(\sprintf('Rebasing onto %s in %s.', $main, $worktree));

        if (!$this->runner->run(['git', 'rebase', $main], $worktree)->successful()) {
            $this->runner->run(['git', 'rebase', '--abort'], $worktree);

            return $this->stop($output, MergeFailure::Rebase, 'The rebase failed; it was aborted.');
        }

        return $this->install($output, $lock, $worktree, $before, 'HEAD');
    }

    /**
     * Runs each gate of the worktree's `gates` script but Infection, the full PHPStan among them.
     */
    private function gates(OutputInterface $output, MergeLock $lock, string $worktree): ?MergeFailure
    {
        $gates = $this->gatesOf($worktree);

        if ([] === $gates) {
            return $this->stop($output, MergeFailure::Gates, \sprintf('The composer.json of %s has neither a gates script nor a phpstan script.', $worktree));
        }

        $temporary = $worktree.'/var/tmp';

        if (!is_dir($temporary)) {
            mkdir($temporary, recursive: true);
        }

        foreach ($gates as $gate) {
            $lock->touch();
            $output->writeln(\sprintf('Gate %s.', $gate));

            if (!$this->runner->run([...$this->composer(), 'run-script', $gate], $worktree, ['TMPDIR' => $temporary])->successful()) {
                return $this->stop($output, MergeFailure::Gates, \sprintf('The gate %s failed.', $gate));
            }
        }

        return null;
    }

    /**
     * The scripts the `gates` script runs, Infection left out, then `phpstan` when the gates do not name it.
     *
     * @return list<string>
     */
    private function gatesOf(string $directory): array
    {
        $composer = json_decode(is_file($directory.'/composer.json') ? Files::read($directory.'/composer.json') : '', true);
        $scripts = \is_array($composer) && \is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
        $gates = [];

        foreach ((array) ($scripts['gates'] ?? []) as $entry) {
            if (\is_string($entry) && str_starts_with($entry, '@') && !str_starts_with($entry, '@infection')) {
                $gates[] = substr($entry, 1);
            }
        }

        if (!\in_array('phpstan', $gates, true) && \array_key_exists('phpstan', $scripts)) {
            $gates[] = 'phpstan';
        }

        return $gates;
    }

    /**
     * Runs `composer install` in the directory when a lock file changed between the two revisions.
     */
    private function install(OutputInterface $output, MergeLock $lock, string $directory, string $from, string $to): ?MergeFailure
    {
        if ($this->git(['diff', '--quiet', $from, $to, '--', ...self::LOCK_FILES], $directory)->successful()) {
            return null;
        }

        $lock->touch();
        $output->writeln(\sprintf('composer.lock changed: composer install in %s.', $directory));

        return $this->runner->run([...$this->composer(), 'install', '--no-interaction', '--no-progress'], $directory)->successful()
            ? null
            : $this->stop($output, MergeFailure::Composer, \sprintf('composer install failed in %s.', $directory));
    }

    /**
     * A branch main already holds: its worktree and branch are removed when its merge commit is found.
     */
    private function alreadyMerged(OutputInterface $output, MergeLock $lock, string $top, string $branch, string $main, string $lot, string $tip, ?string $worktree): ?MergeFailure
    {
        $log = $this->git(['log', '--merges', '--max-count=500', '--format=%H %P', 'HEAD'], $top)->output;

        foreach (explode("\n", $log) as $line) {
            $fields = explode(' ', trim($line));

            if (3 === \count($fields) && $fields[2] === $tip && $this->isMergeCommit($top, $fields[0], $fields[1], $tip, $lot)) {
                $output->writeln(\sprintf('%s is already merged by %s.', $branch, substr($fields[0], 0, 12)));

                return $this->install($output, $lock, $top, $fields[1], $fields[0]) ?? $this->cleanup($output, $lock, $top, $branch, $worktree === $top ? null : $worktree);
            }
        }

        return $this->stop($output, MergeFailure::Usage, \sprintf('%s has no commit that %s lacks, and no merge commit of lot %s.', $branch, $main, $lot));
    }

    private function isMergeCommit(string $top, string $commit, string $first, string $second, string $lot): bool
    {
        $result = $this->git(['log', '-1', '--format=%P%n%s', $commit], $top);
        $lines = explode("\n", $result->output);

        return $result->successful() && $first.' '.$second === $lines[0] && MergeMessage::subject($lot) === ($lines[1] ?? '');
    }

    private function cleanup(OutputInterface $output, MergeLock $lock, string $top, string $branch, ?string $worktree): ?MergeFailure
    {
        $lock->touch();

        if (null !== $worktree && !$this->runner->run(['git', 'worktree', 'remove', $worktree], $top)->successful()) {
            return $this->stop($output, MergeFailure::Cleanup, \sprintf('git worktree remove %s failed; the branch %s is kept.', $worktree, $branch));
        }

        if (!$this->runner->run(['git', 'branch', '-d', $branch], $top)->successful()) {
            return $this->stop($output, MergeFailure::Cleanup, \sprintf('git branch -d %s failed.', $branch));
        }

        $output->writeln(\sprintf('Merged %s as %s; %sthe branch removed.', $branch, substr((string) $this->revision('HEAD', $top), 0, 12), null === $worktree ? '' : 'its worktree and '));

        return null;
    }

    /**
     * @param non-empty-list<string> $arguments
     */
    private function git(array $arguments, string $directory): ProcessResult
    {
        return $this->runner->capture(['git', ...$arguments], $directory);
    }

    private function revision(string $reference, string $directory): ?string
    {
        $result = $this->git(['rev-parse', '--verify', '--quiet', $reference.'^{commit}'], $directory);
        $sha = trim($result->output);

        return $result->successful() && 1 === preg_match('/^[0-9a-f]{40,64}$/', $sha) ? $sha : null;
    }

    private function isBranchName(string $name): bool
    {
        return 1 === preg_match('#^[A-Za-z0-9_][A-Za-z0-9._/-]*$#', $name) && !str_contains($name, '..') && !str_ends_with($name, '/') && !str_ends_with($name, '.lock');
    }

    private function stop(OutputInterface $output, MergeFailure $failure, string $message): MergeFailure
    {
        $output->writeln($message);
        $output->writeln(\sprintf('Stopped: %s (exit code %d). Nothing was removed.', $failure->describe(), $failure->value));

        return $failure;
    }

    private function fail(OutputInterface $output, MergeFailure $failure, string $message): int
    {
        return $this->stop($output, $failure, $message)->value;
    }
}
