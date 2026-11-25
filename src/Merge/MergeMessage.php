<?php

declare(strict_types=1);

namespace Phil\DevTools\Merge;

/**
 * The message of the merge commit of a lot: « Fusion du lot <lot> », a blank line, then one bullet per commit of the
 * branch, oldest first, its subject as it is.
 */
final class MergeMessage
{
    public const string PREFIX = 'Fusion du lot ';

    /**
     * The lot of a branch: its name without the `b/` of the round branches.
     */
    public static function lotOf(string $branch): string
    {
        return str_starts_with($branch, 'b/') && \strlen($branch) > 2 ? substr($branch, 2) : $branch;
    }

    public static function subject(string $lot): string
    {
        return self::PREFIX.$lot;
    }

    /**
     * @param list<string> $subjects the subjects of the commits, oldest first
     */
    public static function build(string $lot, array $subjects): string
    {
        $bullets = array_map(static fn (string $subject): string => '- '.$subject, array_filter(array_map(trim(...), $subjects), static fn (string $subject): bool => '' !== $subject));

        return self::subject($lot).([] === $bullets ? '' : "\n\n".implode("\n", $bullets))."\n";
    }
}
