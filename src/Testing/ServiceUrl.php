<?php

declare(strict_types=1);

namespace Phil\DevTools\Testing;

/**
 * Reads the URL of a real server from the environment, for an integration test that is skipped without it:
 *
 * ```php
 * $url = ServiceUrl::first('PHIL_EXAMPLE_TEST_DATABASE_URL', 'PHIL_TEST_POSTGRESQL_URL')
 *     ?? self::markTestSkipped('No PostgreSQL to test against.');
 * ```
 *
 * A package that needs a variable of its own names it first and falls back on the shared one.
 */
final class ServiceUrl
{
    /**
     * The first of the variables that is set and not empty, or null.
     */
    public static function first(string ...$variables): ?string
    {
        foreach ($variables as $variable) {
            $value = getenv($variable);

            if (\is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return null;
    }
}
