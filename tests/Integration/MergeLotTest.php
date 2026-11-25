<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Integration;

use Ergebnis\PHPUnit\SlowTestDetector\Attribute\MaximumDuration;
use Phil\DevTools\Command\MergeLotCommand;
use Phil\DevTools\Console\Application;
use Phil\DevTools\Merge\LockHolder;
use Phil\DevTools\Merge\MergeLock;
use Phil\DevTools\Process\Executables;
use Phil\DevTools\Process\LocalProcessRunner;
use Phil\DevTools\Tests\Unit\Support\LotRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `phil-dev merge-lot` end to end on a throwaway repository, through the runner every command uses: the lock of a
 * process that is gone is taken over, and the lot is merged into main, its worktree and branch removed.
 */
#[CoversClass(MergeLotCommand::class)]
#[CoversClass(MergeLock::class)]
final class MergeLotTest extends TestCase
{
    use LotRepository;

    #[MaximumDuration(10000)]
    public function testTheLockOfAProcessThatIsGoneIsTakenOverAndTheLotMerged(): void
    {
        if (null === Executables::fromEnvironment()->find('ps')) {
            self::markTestSkipped('ps is not on the PATH: a process that is gone cannot be told from one alive.');
        }

        $this->lotRepository();
        $this->put($this->main.'/main.txt', "Main\n");
        $this->commitIn($this->main, 'Avance de main');
        $gone = (int) new LocalProcessRunner()->capture([\PHP_BINARY, '-r', 'echo getmypid();'], $this->main)->output;
        $lock = $this->main.'/.git/fusion-lot.lock';
        file_put_contents($lock, new LockHolder($gone, 'r1-lot', 'b/r1-lot', '2026-11-25T10:00:00+00:00')->toJson());
        $tester = new CommandTester(new Application($this->main)->find('merge-lot'));

        $status = $this->inRepositoryEnvironment(static fn (): int => $tester->execute(['branch' => 'b/r1-lot']));

        self::assertSame(0, $status, $tester->getDisplay());
        self::assertStringStartsWith('Took over the merge lock of lot r1-lot (branch b/r1-lot, PID '.$gone.", started 2026-11-25T10:00:00+00:00): its process is gone.\nIts merge is run again.\n", $tester->getDisplay());
        self::assertSame("Fusion du lot r1-lot\n\n- Ajout du lot", $this->repositoryGit($this->main, 'log', '-1', '--format=%B'));
        self::assertSame('Avance de main', $this->repositoryGit($this->main, 'log', '-1', '--format=%s', 'HEAD^2^'));
        self::assertSame(['repo-wt-r1-lot: run-script cs', 'repo-wt-r1-lot: run-script phpstan', 'repo-wt-r1-lot: run-script test'], $this->composerCalls());
        self::assertDirectoryDoesNotExist($this->worktree);
        self::assertSame('', $this->repositoryGit($this->main, 'branch', '--list', 'b/r1-lot'));
        self::assertFileDoesNotExist($lock);
    }
}
