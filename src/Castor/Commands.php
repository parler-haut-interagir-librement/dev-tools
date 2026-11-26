<?php

declare(strict_types=1);

namespace Phil\DevTools\Castor;

/**
 * The command lines and results the common Castor tasks build and read, kept apart from Castor so they are tested on
 * their own. The class depends on nothing but PHP: the task file requires it by its path.
 */
final class Commands
{
    /**
     * One exit code for several steps: 0 only when every step succeeded.
     *
     * Exit codes are never summed: a shell keeps them modulo 256, so 128 + 128 would read as a success.
     */
    public static function aggregate(int ...$exitCodes): int
    {
        return array_any($exitCodes, static fn (int $exitCode): bool => 0 !== $exitCode) ? 1 : 0;
    }

    /**
     * The PHPUnit command of some suites, with a filter and more options as the person typed them.
     *
     * @param list<string> $suites
     */
    public static function phpunit(string $phpunit, array $suites, string $filter = '', string $options = ''): string
    {
        $command = \sprintf('%s --testsuite=%s', $phpunit, escapeshellarg(implode(',', $suites)));

        if ('' !== $filter) {
            $command .= ' --filter='.escapeshellarg($filter);
        }

        return trim($command.' '.$options);
    }

    /**
     * The percentage of covered lines of a PHPUnit text coverage report: the "Lines:" of its summary, or null when the
     * report has none.
     */
    public static function coveredLines(string $report): ?float
    {
        if (1 !== preg_match('/^\h*Lines:\h+(\d{1,3}(?:\.\d+)?)%/m', $report, $match)) {
            return null;
        }

        return (float) $match[1];
    }

    /**
     * Whether a report reaches a threshold. A report without a summary never does: a run that covered nothing, or
     * wrote nothing, is not a pass.
     */
    public static function reaches(string $report, int $threshold): bool
    {
        $covered = self::coveredLines($report);

        return null !== $covered && $covered >= $threshold;
    }
}
