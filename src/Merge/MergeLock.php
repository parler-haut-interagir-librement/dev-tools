<?php

declare(strict_types=1);

namespace Phil\DevTools\Merge;

use Phil\DevTools\Exception\MergeLockHeld;
use Phil\DevTools\Process\ProcessRunner;
use RuntimeException;

/**
 * The lock of the merges of one repository, a file in its common git directory that holds the PID of the merge, when
 * it started, its lot and its branch, in JSON. One merge at a time: the file is created only when it does not exist.
 *
 * A lock is taken over when its process is gone, or when it has not moved for 30 minutes — the merge touches it at each
 * step. The PID is looked up with `ps`; when `ps` is missing or cannot tell, the process counts as alive and the 30
 * minutes decide.
 */
final class MergeLock
{
    /**
     * How long, in seconds, a lock may stay without movement before another merge takes it over.
     */
    public const int STALE_AFTER = 1800;

    private bool $held = false;

    /**
     * @param string|null $ps the path of `ps`, null when the machine has none
     */
    public function __construct(private readonly string $path, private readonly ProcessRunner $runner, private readonly ?string $ps)
    {
    }

    /**
     * Takes the lock for `$holder`; returns the takeover when a stopped merge held it.
     *
     * @throws MergeLockHeld when a merge alive and moving holds it
     */
    public function acquire(LockHolder $holder, int $now): ?LockTakeover
    {
        if ($this->create($holder)) {
            return null;
        }

        $previous = $this->read();
        $reason = $this->staleness($previous, $now);

        if (null === $reason) {
            throw new MergeLockHeld($previous);
        }

        @unlink($this->path);

        if (!$this->create($holder)) {
            // Another merge took it over between the two.
            throw new MergeLockHeld($this->read());
        }

        return new LockTakeover($previous, $reason);
    }

    /**
     * Marks the lock as moving: the merge reached another step.
     */
    public function touch(): void
    {
        if ($this->held) {
            touch($this->path);
        }
    }

    /**
     * Removes the lock, when this merge still holds it: a lock taken over by another merge is left to it.
     */
    public function release(int $pid): void
    {
        if (!$this->held) {
            return;
        }

        $this->held = false;

        if (is_file($this->path) && $this->read()->pid === $pid) {
            unlink($this->path);
        }
    }

    public function isHeld(): bool
    {
        return $this->held;
    }

    /**
     * @phpstan-impure
     */
    private function create(LockHolder $holder): bool
    {
        $handle = @fopen($this->path, 'x');

        if (false === $handle) {
            if (!is_file($this->path)) {
                throw new RuntimeException(\sprintf('Cannot create the merge lock %s.', $this->path));
            }

            return false;
        }

        fwrite($handle, $holder->toJson());
        fclose($handle);
        $this->held = true;

        return true;
    }

    private function read(): LockHolder
    {
        $content = @file_get_contents($this->path);

        return LockHolder::fromJson(false === $content ? '' : $content);
    }

    /**
     * Why the lock can be taken over, or null while its merge is alive and moving.
     */
    private function staleness(LockHolder $holder, int $now): ?string
    {
        if (null !== $holder->pid && !$this->isAlive($holder->pid)) {
            return 'its process is gone';
        }

        clearstatcache(true, $this->path);
        $moved = @filemtime($this->path);
        $idle = false === $moved ? self::STALE_AFTER : $now - $moved;

        return $idle >= self::STALE_AFTER ? \sprintf('it has not moved for %d minutes', intdiv($idle, 60)) : null;
    }

    private function isAlive(int $pid): bool
    {
        if (null === $this->ps) {
            return true;
        }

        $result = $this->runner->capture([$this->ps, '-p', (string) $pid, '-o', 'pid='], '/');

        // ps exits with 1 and prints nothing for a PID no process has; any other answer leaves it to the 30 minutes.
        return 1 !== $result->exitCode || '' !== trim($result->output);
    }
}
