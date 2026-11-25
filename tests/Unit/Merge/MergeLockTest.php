<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Merge;

use Phil\DevTools\Exception\MergeLockHeld;
use Phil\DevTools\Merge\LockHolder;
use Phil\DevTools\Merge\MergeLock;
use Phil\DevTools\Process\ProcessResult;
use Phil\DevTools\Tests\Unit\Support\RecordingRunner;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MergeLock::class)]
#[CoversClass(MergeLockHeld::class)]
final class MergeLockTest extends TestCase
{
    use TemporaryDirectory;

    private const int NOW = 1_800_000_000;

    private const string PS = '/usr/bin/ps';

    public function testAFreeLockIsTakenAndReleased(): void
    {
        $path = $this->temporaryDirectory().'/fusion-lot.lock';
        $runner = new RecordingRunner();
        $lock = new MergeLock($path, $runner, self::PS);

        self::assertNull($lock->acquire($this->holder(7), self::NOW));
        self::assertTrue($lock->isHeld());
        self::assertSame($this->holder(7)->toJson(), file_get_contents($path));
        self::assertSame([], $runner->runs);

        $lock->release(7);

        self::assertFalse($lock->isHeld());
        self::assertFileDoesNotExist($path);
    }

    /**
     * @param list<ProcessResult> $ps
     */
    #[DataProvider('heldLocks')]
    public function testALockAliveAndMovingIsHeld(?int $pid, array $ps, int $idle): void
    {
        $path = $this->temporaryDirectory().'/fusion-lot.lock';
        $previous = $this->holder($pid, 'r9-other');
        file_put_contents($path, $previous->toJson());
        touch($path, self::NOW - $idle);
        $runner = new RecordingRunner($ps);
        $lock = new MergeLock($path, $runner, self::PS);

        try {
            $lock->acquire($this->holder(7), self::NOW);
            self::fail('The lock was taken.');
        } catch (MergeLockHeld $mergeLockHeld) {
            self::assertEquals($previous, $mergeLockHeld->holder);
            self::assertSame('Another merge holds the lock: '.$previous->describe().'.', $mergeLockHeld->getMessage());
        }

        self::assertFalse($lock->isHeld());
        self::assertSame($previous->toJson(), file_get_contents($path));
        self::assertSame(null === $pid ? [] : [self::PS.' -p '.$pid.' -o pid='], $runner->commands());
        self::assertSame(null === $pid ? [] : ['/'], array_column($runner->runs, 'directory'));
    }

    /**
     * @return iterable<string, array{int|null, list<ProcessResult>, int}>
     */
    public static function heldLocks(): iterable
    {
        yield 'a live process' => [123, [new ProcessResult(0, "  123\n")], 0];
        yield 'a live process, almost 30 minutes' => [123, [new ProcessResult(0, '123')], MergeLock::STALE_AFTER - 1];
        yield 'ps cannot tell' => [123, [new ProcessResult(127)], 60];
        yield 'ps fails with output' => [123, [new ProcessResult(1, 'ps: error')], 60];
        yield 'no process named' => [null, [], 60];
    }

    /**
     * @param list<ProcessResult> $ps
     */
    #[DataProvider('staleLocks')]
    public function testAStoppedMergeHasItsLockTakenOver(?int $pid, array $ps, int $idle, string $reason): void
    {
        $path = $this->temporaryDirectory().'/fusion-lot.lock';
        $previous = $this->holder($pid, 'r9-other');
        file_put_contents($path, $previous->toJson());
        touch($path, self::NOW - $idle);
        $lock = new MergeLock($path, new RecordingRunner($ps), self::PS);

        $takeover = $lock->acquire($this->holder(7), self::NOW);

        self::assertNotNull($takeover);
        self::assertEquals($previous, $takeover->previous);
        self::assertSame($reason, $takeover->reason);
        self::assertTrue($lock->isHeld());
        self::assertSame($this->holder(7)->toJson(), file_get_contents($path));
    }

    /**
     * @return iterable<string, array{int|null, list<ProcessResult>, int, string}>
     */
    public static function staleLocks(): iterable
    {
        yield 'a dead process' => [123, [new ProcessResult(1, " \n")], 0, 'its process is gone'];
        yield '30 minutes' => [123, [new ProcessResult(0, '123')], MergeLock::STALE_AFTER, 'it has not moved for 30 minutes'];
        yield 'no process, 2 hours' => [null, [], 7260, 'it has not moved for 121 minutes'];
    }

    public function testWithoutPsALiveProcessIsLeftToTheThirtyMinutes(): void
    {
        $path = $this->temporaryDirectory().'/fusion-lot.lock';
        file_put_contents($path, $this->holder(123, 'r9-other')->toJson());
        touch($path, self::NOW - 60);
        $runner = new RecordingRunner([new ProcessResult(1)]);

        try {
            new MergeLock($path, $runner, null)->acquire($this->holder(7), self::NOW);
            self::fail('The lock was taken.');
        } catch (MergeLockHeld) {
            self::assertSame([], $runner->runs);
        }

        touch($path, self::NOW - MergeLock::STALE_AFTER);

        self::assertSame('it has not moved for 30 minutes', new MergeLock($path, $runner, null)->acquire($this->holder(7), self::NOW)?->reason);
    }

    public function testTouchMovesOnlyAHeldLock(): void
    {
        $directory = $this->temporaryDirectory();
        $path = $directory.'/fusion-lot.lock';
        $lock = new MergeLock($path, new RecordingRunner(), self::PS);

        $lock->touch();
        self::assertFileDoesNotExist($path);

        $lock->acquire($this->holder(7), self::NOW);
        touch($path, time() - 3600);
        $lock->touch();
        clearstatcache(true, $path);

        self::assertGreaterThan(time() - 60, filemtime($path));
    }

    public function testALockTakenOverByAnotherMergeIsLeftToIt(): void
    {
        $path = $this->temporaryDirectory().'/fusion-lot.lock';
        $lock = new MergeLock($path, new RecordingRunner(), self::PS);
        $lock->acquire($this->holder(7), self::NOW);
        file_put_contents($path, $this->holder(8)->toJson());

        $lock->release(7);

        self::assertFalse($lock->isHeld());
        self::assertSame($this->holder(8)->toJson(), file_get_contents($path));
    }

    public function testALockNeverTakenIsNotReleased(): void
    {
        $path = $this->temporaryDirectory().'/fusion-lot.lock';
        file_put_contents($path, $this->holder(7)->toJson());

        new MergeLock($path, new RecordingRunner(), self::PS)->release(7);

        self::assertFileExists($path);
    }

    public function testAReleasedLockAlreadyGoneIsFine(): void
    {
        $path = $this->temporaryDirectory().'/fusion-lot.lock';
        $lock = new MergeLock($path, new RecordingRunner(), self::PS);
        $lock->acquire($this->holder(7), self::NOW);
        unlink($path);

        $lock->release(7);

        self::assertFalse($lock->isHeld());
    }

    public function testALockThatCannotBeCreated(): void
    {
        $path = $this->temporaryDirectory().'/missing/fusion-lot.lock';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Cannot create the merge lock '.$path.'.');

        new MergeLock($path, new RecordingRunner(), self::PS)->acquire($this->holder(7), self::NOW);
    }

    private function holder(?int $pid, string $lot = 'r1-lot'): LockHolder
    {
        return new LockHolder($pid, $lot, 'b/'.$lot, '2026-11-25T10:00:00+00:00');
    }
}
