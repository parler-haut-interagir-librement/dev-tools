# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network. In the monorepo, Infection holds
the package at 100 % of covered mutants.

A test that needs a real server — MySQL, PostgreSQL, Gotenberg — reads its URL from an environment variable and is
skipped without it: `PHIL_TEST_MYSQL_URL`, `PHIL_TEST_POSTGRESQL_URL` and `PHIL_GOTENBERG_URL`.

A test that calls an HTTP service is marked `#[UseRecord]` and replays the HAR file committed beside it, offline;
`SYMFONY_HTTP_RECORDER=record` records it again against the real service, with its secrets masked.
