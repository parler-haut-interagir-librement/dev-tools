<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Merge;

use Phil\DevTools\Merge\Worktrees;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Worktrees::class)]
final class WorktreesTest extends TestCase
{
    public function testTheDirectoryOfEachBranch(): void
    {
        $porcelain = "worktree /repo\0HEAD 1111\0branch refs/heads/main\0\0"
            ."worktree /repo-wt branch refs/heads/x\0HEAD 2222\0branch refs/heads/b/r1-lot\0\0"
            ."worktree /detached\0HEAD 3333\0detached\0\0"
            ."worktree /bare\0bare\0\0";

        self::assertSame(['main' => '/repo', 'b/r1-lot' => '/repo-wt branch refs/heads/x'], Worktrees::parse($porcelain));
    }

    public function testABranchLineOutsideARecordIsIgnored(): void
    {
        self::assertSame([], Worktrees::parse("branch refs/heads/main\0\0worktree /a\0\0branch refs/heads/b\0\0"));
        self::assertSame([], Worktrees::parse(''));
    }

    public function testOnlyLocalBranches(): void
    {
        self::assertSame([], Worktrees::parse("worktree /a\0branch refs/remotes/origin/main\0\0"));
    }
}
