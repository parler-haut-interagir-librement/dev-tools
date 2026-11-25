<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Command;

use Ergebnis\PHPUnit\SlowTestDetector\Attribute\MaximumDuration;
use Override;
use Phil\DevTools\Command\MergeLotCommand;
use Phil\DevTools\Console\Application;
use Phil\DevTools\Merge\LockHolder;
use Phil\DevTools\Merge\MergeFailure;
use Phil\DevTools\Process\LocalProcessRunner;
use Phil\DevTools\Process\ProcessResult;
use Phil\DevTools\Process\ProcessRunner;
use Phil\DevTools\Tests\Unit\Support\LotRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The merge of a lot on a throwaway repository, with git itself: what each step leaves behind.
 */
#[CoversClass(MergeLotCommand::class)]
final class MergeLotCommandTest extends TestCase
{
    use LotRepository;

    #[MaximumDuration(5000)]
    public function testItMergesTheLotAndRemovesItsWorktreeAndBranch(): void
    {
        $this->lotRepository();
        $this->put($this->worktree.'/second.txt', "Second\n");
        $this->commitIn($this->worktree, 'Deuxième commit du lot');
        $before = $this->repositoryGit($this->main, 'rev-parse', 'HEAD');
        $tip = $this->repositoryGit($this->main, 'rev-parse', 'b/r1-lot');

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(0, $status, $display);
        self::assertSame("Fusion du lot r1-lot\n\n- Ajout du lot\n- Deuxième commit du lot", $this->repositoryGit($this->main, 'log', '-1', '--format=%B'));
        self::assertSame($before.' '.$tip, $this->repositoryGit($this->main, 'log', '-1', '--format=%P'));
        self::assertSame(['repo-wt-r1-lot: run-script cs', 'repo-wt-r1-lot: run-script phpstan', 'repo-wt-r1-lot: run-script test'], $this->composerCalls());
        self::assertDirectoryDoesNotExist($this->worktree);
        self::assertSame('', $this->repositoryGit($this->main, 'branch', '--list', 'b/r1-lot'));
        self::assertFileDoesNotExist($this->main.'/.git/fusion-lot.lock');
        self::assertSame('', $this->repositoryGit($this->main, 'status', '--porcelain'));
        $merge = substr($this->repositoryGit($this->main, 'rev-parse', 'HEAD'), 0, 12);
        self::assertSame(
            "Rebasing onto main in {$this->worktree}.\nGate cs.\nGate phpstan.\nGate test.\nMerging b/r1-lot into main.\nMerged b/r1-lot as {$merge}; its worktree and the branch removed.\n",
            $display,
        );
    }

    #[MaximumDuration(5000)]
    public function testTheBranchIsRebasedOntoMainFirst(): void
    {
        $this->lotRepository();
        $this->put($this->main.'/main.txt', "Main\n");
        $this->commitIn($this->main, 'Avance de main');
        $advanced = $this->repositoryGit($this->main, 'rev-parse', 'HEAD');

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot', '--lot' => 'autre-nom']);

        self::assertSame(0, $status, $display);
        self::assertSame($advanced, $this->repositoryGit($this->main, 'rev-parse', 'HEAD^1'));
        self::assertSame($advanced, $this->repositoryGit($this->main, 'rev-parse', 'HEAD^2^'));
        self::assertSame('Fusion du lot autre-nom', $this->repositoryGit($this->main, 'log', '-1', '--format=%s'));
    }

    #[MaximumDuration(5000)]
    public function testPhpstanIsAddedWhenTheGatesDoNotNameIt(): void
    {
        $this->lotRepository(['@cs', 'echo', '@infection:changed']);

        [$status] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(0, $status);
        self::assertSame(['repo-wt-r1-lot: run-script cs', 'repo-wt-r1-lot: run-script phpstan'], $this->composerCalls());
    }

    #[MaximumDuration(5000)]
    public function testWithoutGatesNothingIsMerged(): void
    {
        $this->lotRepository([]);
        $this->put($this->worktree.'/composer.json', "{\"scripts\": {\"cs\": \"true\"}}\n");
        $this->commitIn($this->worktree, 'Sans portes');

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(MergeFailure::Gates->value, $status);
        self::assertStringContainsString("The composer.json of {$this->worktree} has neither a gates script nor a phpstan script.\n", $display);
        self::assertDirectoryExists($this->worktree);
    }

    #[MaximumDuration(5000)]
    public function testAFailedGateStopsTheMergeAndRemovesNothing(): void
    {
        $this->lotRepository();
        $before = $this->repositoryGit($this->main, 'rev-parse', 'HEAD');

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot'], 'phpstan');

        self::assertSame(7, $status);
        self::assertSame(['repo-wt-r1-lot: run-script cs', 'repo-wt-r1-lot: run-script phpstan'], $this->composerCalls());
        self::assertStringEndsWith("Gate phpstan.\nThe gate phpstan failed.\nStopped: a gate failed (exit code 7). Nothing was removed.\n", $display);
        self::assertSame($before, $this->repositoryGit($this->main, 'rev-parse', 'HEAD'));
        self::assertDirectoryExists($this->worktree);
        self::assertSame('b/r1-lot', trim($this->repositoryGit($this->main, 'branch', '--list', '--format=%(refname:short)', 'b/r1-lot')));
        self::assertFileDoesNotExist($this->main.'/.git/fusion-lot.lock');
    }

    #[MaximumDuration(5000)]
    public function testModifiedTrackedFilesInMainStopTheMerge(): void
    {
        $this->lotRepository();
        $this->put($this->main.'/README.md', "Changed\n");
        $this->put($this->main.'/untracked.txt', "Untracked files do not count\n");

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(4, $status);
        self::assertSame("Tracked files are modified in {$this->main}.\nStopped: tracked files modified (exit code 4). Nothing was removed.\n", $display);
        self::assertSame([], $this->composerCalls());
    }

    #[MaximumDuration(5000)]
    public function testModifiedTrackedFilesInTheWorktreeStopTheMerge(): void
    {
        $this->lotRepository();
        $this->put($this->worktree.'/lot.txt', "Changed\n");

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(4, $status);
        self::assertStringStartsWith("Tracked files are modified in {$this->worktree}.\n", $display);
    }

    #[MaximumDuration(5000)]
    public function testARebaseInProgressStopsTheMerge(): void
    {
        $this->lotRepository();
        mkdir($this->repositoryGit($this->worktree, 'rev-parse', '--path-format=absolute', '--git-path', 'rebase-apply'));

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(4, $status);
        self::assertStringStartsWith("A rebase is in progress in {$this->worktree}.\n", $display);
    }

    #[MaximumDuration(5000)]
    public function testAConflictingRebaseIsAbortedAndStopsTheMerge(): void
    {
        $this->lotRepository();
        $tip = $this->repositoryGit($this->main, 'rev-parse', 'b/r1-lot');
        $this->put($this->main.'/lot.txt', "Conflict\n");
        $this->commitIn($this->main, 'Conflit');

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(5, $status);
        self::assertStringEndsWith("The rebase failed; it was aborted.\nStopped: the rebase failed (exit code 5). Nothing was removed.\n", $display);
        self::assertSame($tip, $this->repositoryGit($this->worktree, 'rev-parse', 'HEAD'));
        self::assertSame('', $this->repositoryGit($this->worktree, 'status', '--porcelain'));
        self::assertSame([], $this->composerCalls());
    }

    #[MaximumDuration(5000)]
    public function testAFailedMergeIsAbortedAndLeavesMainAsItWas(): void
    {
        $this->lotRepository();
        $hooks = $this->repositoryGit($this->main, 'rev-parse', '--path-format=absolute', '--git-path', 'hooks');
        $this->put($hooks.'/pre-merge-commit', "#!/bin/sh\nexit 1\n");
        chmod($hooks.'/pre-merge-commit', 0o755);
        $before = $this->repositoryGit($this->main, 'rev-parse', 'HEAD');

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(8, $status);
        self::assertStringEndsWith("git merge failed; it was aborted.\nStopped: the merge failed (exit code 8). Nothing was removed.\n", $display);
        self::assertSame($before, $this->repositoryGit($this->main, 'rev-parse', 'HEAD'));
        self::assertSame('', $this->repositoryGit($this->main, 'status', '--porcelain'));
        self::assertDirectoryExists($this->worktree);
    }

    #[MaximumDuration(5000)]
    public function testAChangedLockFileIsInstalledInTheWorktreeAndInMain(): void
    {
        $this->lotRepository();
        $this->put($this->worktree.'/composer.lock', "{}\n");
        $this->commitIn($this->worktree, 'Verrou du lot');
        $this->put($this->main.'/vendor-bin/tool/composer.lock', "{}\n");
        $this->commitIn($this->main, 'Verrou d’un outil');

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(0, $status, $display);
        self::assertSame([
            'repo-wt-r1-lot: install --no-interaction --no-progress',
            'repo-wt-r1-lot: run-script cs',
            'repo-wt-r1-lot: run-script phpstan',
            'repo-wt-r1-lot: run-script test',
            'repo: install --no-interaction --no-progress',
        ], $this->composerCalls());
        self::assertStringContainsString("composer.lock changed: composer install in {$this->main}.\n", $display);
    }

    #[MaximumDuration(5000)]
    public function testAFailedInstallInMainKeepsTheWorktreeAndTheBranch(): void
    {
        $this->lotRepository();
        $this->put($this->worktree.'/composer.lock', "{}\n");
        $this->commitIn($this->worktree, 'Verrou du lot');

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot'], 'install');

        self::assertSame(6, $status);
        self::assertStringEndsWith("composer install failed in {$this->main}.\nStopped: composer install failed (exit code 6). Nothing was removed.\n", $display);
        self::assertSame('Fusion du lot r1-lot', $this->repositoryGit($this->main, 'log', '-1', '--format=%s'));
        self::assertDirectoryExists($this->worktree);
    }

    #[MaximumDuration(5000)]
    public function testAFailedCleanupKeepsTheBranchAndARunAgainFinishesIt(): void
    {
        $this->lotRepository();
        $this->put($this->worktree.'/stray.txt', "Untracked\n");

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(10, $status);
        self::assertStringEndsWith("git worktree remove {$this->worktree} failed; the branch b/r1-lot is kept.\nStopped: the cleanup failed (exit code 10). Nothing was removed.\n", $display);
        self::assertDirectoryExists($this->worktree);
        $merge = $this->repositoryGit($this->main, 'rev-parse', 'HEAD');

        unlink($this->worktree.'/stray.txt');
        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(0, $status, $display);
        self::assertStringStartsWith('b/r1-lot is already merged by '.substr($merge, 0, 12).".\n", $display);
        self::assertSame($merge, $this->repositoryGit($this->main, 'rev-parse', 'HEAD'));
        self::assertDirectoryDoesNotExist($this->worktree);
        self::assertSame('', $this->repositoryGit($this->main, 'branch', '--list', 'b/r1-lot'));
        self::assertCount(3, $this->composerCalls());
    }

    #[MaximumDuration(5000)]
    public function testAMergedBranchWithoutWorktreeHasOnlyItsBranchRemoved(): void
    {
        $this->lotRepository();
        $this->repositoryGit($this->main, 'merge', '--no-ff', '-m', 'Fusion du lot r1-lot', 'b/r1-lot');
        $this->repositoryGit($this->main, 'worktree', 'remove', $this->worktree);

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(0, $status, $display);
        self::assertStringEndsWith('; the branch removed.'."\n", $display);
        self::assertSame('', $this->repositoryGit($this->main, 'branch', '--list', 'b/r1-lot'));
    }

    #[MaximumDuration(5000)]
    public function testABranchMergedWithAnotherMessageIsLeftAlone(): void
    {
        $this->lotRepository();
        $this->repositoryGit($this->main, 'merge', '--no-ff', '-m', 'Fusion du lot autre', 'b/r1-lot');

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(2, $status);
        self::assertStringStartsWith("b/r1-lot has no commit that main lacks, and no merge commit of lot r1-lot.\n", $display);
        self::assertDirectoryExists($this->worktree);
    }

    #[MaximumDuration(5000)]
    public function testALockHeldByALiveMergeStopsTheMerge(): void
    {
        $this->lotRepository();
        $lock = $this->main.'/.git/fusion-lot.lock';
        $content = new LockHolder((int) getmypid(), 'r9-other', 'b/r9-other', '2026-11-25T10:00:00+00:00')->toJson();
        file_put_contents($lock, $content);

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(3, $status);
        self::assertSame('Another merge holds the lock: lot r9-other (branch b/r9-other, PID '.getmypid().", started 2026-11-25T10:00:00+00:00).\nStopped: locked by another merge (exit code 3). Nothing was removed.\n", $display);
        self::assertSame($content, file_get_contents($lock));
    }

    #[MaximumDuration(5000)]
    public function testALockWithoutMovementIsTakenOverForAnotherLot(): void
    {
        $this->lotRepository();
        $lock = $this->main.'/.git/fusion-lot.lock';
        file_put_contents($lock, new LockHolder((int) getmypid(), 'r9-other', 'b/r9-other', 'then')->toJson());
        touch($lock, time() - 3600);

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(0, $status, $display);
        self::assertStringStartsWith(
            'Took over the merge lock of lot r9-other (branch b/r9-other, PID '.getmypid().", started then): it has not moved for 60 minutes.\n"
            ."The merge of lot r9-other did not finish: run phil-dev merge-lot b/r9-other again after this one.\n",
            $display,
        );
        self::assertFileDoesNotExist($lock);
    }

    #[MaximumDuration(5000)]
    public function testAnUnfinishedMergeOfTheSameLotIsAbortedAndRunAgain(): void
    {
        $this->lotRepository();
        $this->repositoryGit($this->main, 'merge', '--no-ff', '--no-commit', 'b/r1-lot');
        $lock = $this->main.'/.git/fusion-lot.lock';
        file_put_contents($lock, new LockHolder(null, 'r1-lot', 'b/r1-lot', 'then')->toJson());
        touch($lock, time() - 1800);

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(0, $status, $display);
        self::assertStringStartsWith(
            "Took over the merge lock of lot r1-lot (branch b/r1-lot, PID ?, started then): it has not moved for 30 minutes.\n"
            ."Its merge is run again.\nAborting the unfinished merge of the lot.\n",
            $display,
        );
        self::assertSame('Fusion du lot r1-lot', $this->repositoryGit($this->main, 'log', '-1', '--format=%s'));
    }

    #[MaximumDuration(5000)]
    public function testAnUnfinishedMergeWithoutTakeoverStopsTheMerge(): void
    {
        $this->lotRepository();
        $this->repositoryGit($this->main, 'merge', '--no-ff', '--no-commit', 'b/r1-lot');

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(4, $status);
        self::assertStringStartsWith("A git merge is in progress in {$this->main}.\n", $display);
        self::assertNotSame('', $this->repositoryGit($this->main, 'rev-parse', '--verify', 'MERGE_HEAD'));
    }

    #[MaximumDuration(5000)]
    public function testAnUnfinishedMergeOfAnotherLotStopsTheMerge(): void
    {
        $this->lotRepository();
        $this->repositoryGit($this->main, 'merge', '--no-ff', '--no-commit', 'b/r1-lot');
        $lock = $this->main.'/.git/fusion-lot.lock';
        file_put_contents($lock, new LockHolder(null, 'r9-other', 'b/r9-other', 'then')->toJson());
        touch($lock, time() - 3600);

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(4, $status);
        self::assertStringContainsString("A git merge is in progress in {$this->main}.\n", $display);
    }

    #[MaximumDuration(5000)]
    public function testABranchWithoutWorktreeIsNotMerged(): void
    {
        $this->lotRepository();
        $this->repositoryGit($this->main, 'worktree', 'remove', $this->worktree);

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot']);

        self::assertSame(2, $status);
        self::assertStringStartsWith("No worktree of its own has b/r1-lot checked out.\n", $display);
    }

    #[MaximumDuration(5000)]
    public function testTheCheckoutMustBeOnMain(): void
    {
        $this->lotRepository();

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot', '--main' => 'develop']);

        self::assertSame(2, $status);
        self::assertStringStartsWith("{$this->main} is not on develop: merge-lot runs from the checkout of develop.\n", $display);
    }

    #[MaximumDuration(5000)]
    public function testAnUnknownBranch(): void
    {
        $this->lotRepository();

        [$status, $display] = $this->mergeLot(['branch' => 'b/r2-none']);

        self::assertSame(2, $status);
        self::assertStringStartsWith("No branch b/r2-none.\n", $display);
    }

    public function testOutsideAGitRepository(): void
    {
        $outside = $this->temporaryDirectory();

        [$status, $display] = $this->mergeLot(['branch' => 'b/r1-lot'], root: $outside);

        self::assertSame(2, $status);
        self::assertSame("{$outside} is not in a git repository.\nStopped: nothing to merge (exit code 2). Nothing was removed.\n", $display);
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('invalidNames')]
    public function testInvalidNames(array $input): void
    {
        [$status, $display] = $this->mergeLot($input, root: $this->temporaryDirectory());

        self::assertSame(2, $status);
        self::assertStringStartsWith("The branch, the main branch and the lot must be plain names.\n", $display);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidNames(): iterable
    {
        yield 'an option as branch' => [['branch' => '--force']];
        yield 'a parent' => [['branch' => 'b/../main']];
        yield 'a trailing slash' => [['branch' => 'b/']];
        yield 'a lock name' => [['branch' => 'b/x.lock']];
        yield 'a space' => [['branch' => 'b/a b']];
        yield 'a main option' => [['branch' => 'b/r1-lot', '--main' => '-x']];
        yield 'a lot on two lines' => [['branch' => 'b/r1-lot', '--lot' => "a\nb"]];
        yield 'an empty lot' => [['branch' => 'b/r1-lot', '--lot' => '']];
    }

    /**
     * @param array<string, string> $input
     *
     * @return array{int, string}
     */
    private function mergeLot(array $input, string $failing = '', ?string $root = null): array
    {
        $tester = new CommandTester(new Application($root ?? $this->main, $this->quietRunner())->find('merge-lot'));

        return $this->inRepositoryEnvironment(static fn (): array => [$tester->execute($input), $tester->getDisplay()], $failing);
    }

    /**
     * Runs each command with its error output dropped: Infection stops its initial run at the first byte on stderr.
     */
    private function quietRunner(): ProcessRunner
    {
        return new class implements ProcessRunner {
            #[Override]
            public function run(array $command, string $directory, array $environment = [], ?int $timeout = null): ProcessResult
            {
                return new ProcessResult($this->capture($command, $directory, $environment)->exitCode);
            }

            #[Override]
            public function capture(array $command, string $directory, array $environment = [], bool $withErrors = false): ProcessResult
            {
                return new LocalProcessRunner()->capture(['/bin/sh', '-c', 'exec "$@" 2>/dev/null', 'sh', ...$command], $directory, $environment);
            }
        };
    }
}
