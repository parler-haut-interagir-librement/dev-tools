<?php

declare(strict_types=1);

/*
 * What merge-lot reads of git and of its lock, on input nobody wrote: each half of the input, cut at its first
 * newline, is read as the content of a merge lock and as the output of `git worktree list --porcelain -z`. Whatever
 * they hold, the lock reads as a holder whose JSON reads back the same, and every worktree is a branch name mapped to
 * a directory, without a warning nor an exception.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\DevTools\Merge\LockHolder;
use Phil\DevTools\Merge\Worktrees;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    error_clear_last();
    [$lock, $worktrees] = array_pad(explode("\n", $input, 2), 2, '');
    $holder = LockHolder::fromJson($lock);

    if (LockHolder::fromJson($holder->toJson())->toJson() !== $holder->toJson()) {
        throw new LogicException(sprintf('The lock "%s" does not read back as itself.', $lock));
    }

    foreach (Worktrees::parse($worktrees) as $branch => $directory) {
        if (!is_string($branch) || '' === $branch || !is_string($directory)) {
            throw new LogicException(sprintf('The worktrees "%s" gave a branch that is not a name.', $worktrees));
        }
    }

    if (null !== error_get_last()) {
        throw new LogicException(sprintf('"%s" raised: %s', $input, error_get_last()['message']));
    }
});
$config->setMaxLen(512);
