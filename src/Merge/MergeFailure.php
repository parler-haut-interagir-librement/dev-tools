<?php

declare(strict_types=1);

namespace Phil\DevTools\Merge;

/**
 * The step at which `phil-dev merge-lot` stopped, as its exit code. Each step stops the merge: what comes after it is
 * not run, and nothing is removed.
 */
enum MergeFailure: int
{
    /** The arguments, the repository or the branch cannot be merged: not a repository, main not checked out, no such branch or worktree. */
    case Usage = 2;

    /** Another merge holds the lock, its process alive and the lock moved less than 30 minutes ago. */
    case Locked = 3;

    /** A tracked file is modified, in the checkout of main or in the worktree of the branch. */
    case Dirty = 4;

    /** The rebase of the branch onto main failed; it was aborted. */
    case Rebase = 5;

    /** `composer install` failed, in the worktree after the rebase or in the checkout of main after the merge. */
    case Composer = 6;

    /** A gate failed in the worktree. */
    case Gates = 7;

    /** `git merge` failed; it was aborted. */
    case Merge = 8;

    /** The commit on main is not the merge commit of the lot. */
    case Verify = 9;

    /** The worktree or the branch could not be removed. */
    case Cleanup = 10;

    public function describe(): string
    {
        return match ($this) {
            self::Usage => 'nothing to merge',
            self::Locked => 'locked by another merge',
            self::Dirty => 'tracked files modified',
            self::Rebase => 'the rebase failed',
            self::Composer => 'composer install failed',
            self::Gates => 'a gate failed',
            self::Merge => 'the merge failed',
            self::Verify => 'the merge commit is not the expected one',
            self::Cleanup => 'the cleanup failed',
        };
    }
}
