<?php

declare(strict_types=1);

namespace Phil\DevTools\Merge;

/**
 * The worktrees of a repository, read from `git worktree list --porcelain -z`: the directory of each checked-out branch.
 */
final class Worktrees
{
    /**
     * @return array<string, string> each local branch checked out, by name, and the directory of its worktree
     */
    public static function parse(string $porcelain): array
    {
        $branches = [];
        $directory = null;

        foreach (explode("\0", $porcelain) as $line) {
            if ('' === $line) {
                // A record ends with an empty field.
                $directory = null;
            } elseif (str_starts_with($line, 'worktree ')) {
                $directory = substr($line, 9);
            } elseif (null !== $directory && str_starts_with($line, 'branch refs/heads/')) {
                $branches[substr($line, 18)] = $directory;
            }
        }

        return $branches;
    }
}
