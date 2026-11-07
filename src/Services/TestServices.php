<?php

declare(strict_types=1);

namespace Phil\DevTools\Services;

use Phil\DevTools\DevTools;

/**
 * The real servers of `resources/compose.yaml` and the environment variables the integration tests read to reach them.
 *
 * | Variable | Server | Default from `phil-dev services env` |
 * |---|---|---|
 * | `PHIL_TEST_MYSQL_URL` | MySQL 8 | `mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4` |
 * | `PHIL_TEST_POSTGRESQL_URL` | PostgreSQL 17 | `postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17` |
 * | `PHIL_GOTENBERG_URL` | Gotenberg 9 | `http://127.0.0.1:3011` |
 */
final class TestServices
{
    public const string PROJECT = 'phil-dev-tools';

    public const string MYSQL = 'PHIL_TEST_MYSQL_URL';

    public const string POSTGRESQL = 'PHIL_TEST_POSTGRESQL_URL';

    public const string GOTENBERG = 'PHIL_GOTENBERG_URL';

    public static function composeFile(): string
    {
        return DevTools::resource('compose.yaml');
    }

    /**
     * The variables of the tests, each from the environment when it is set, else pointing at the servers of the compose
     * file on their ports.
     *
     * @param array<string, string> $environment the environment to read, `getenv()` by default
     *
     * @return array<string, string>
     */
    public static function environment(?array $environment = null): array
    {
        $environment ??= getenv();
        $port = static fn (string $variable, string $default): string => $environment[$variable] ?? $default;

        $defaults = [
            self::MYSQL => 'mysql://phil:phil@127.0.0.1:'.$port('PHIL_DEV_MYSQL_PORT', '13306').'/phil_test?serverVersion=8.4',
            self::POSTGRESQL => 'postgresql://phil:phil@127.0.0.1:'.$port('PHIL_DEV_POSTGRESQL_PORT', '15432').'/phil_test?serverVersion=17',
            self::GOTENBERG => 'http://127.0.0.1:'.$port('PHIL_DEV_GOTENBERG_PORT', '3011'),
        ];

        $variables = [];

        foreach ($defaults as $name => $default) {
            $value = $environment[$name] ?? '';
            $variables[$name] = '' === $value ? $default : $value;
        }

        return $variables;
    }
}
