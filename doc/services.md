# Real servers

The integration tests of a package run against real servers when their URL is in the environment, and are skipped
otherwise, so that `composer test` and `phil-dev check-packages` pass on a machine without them.

```sh
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)"
composer test:services
vendor/bin/phil-dev services down
```

`resources/compose.yaml` holds the servers, under the project name `phil-dev-tools`, bound to 127.0.0.1, with their data
in tmpfs: they start empty and leave nothing behind.

| Server | Image | Port | Variable | Value from `services env` |
|---|---|---|---|---|
| MySQL 8 | `mysql:8.4` | 13306 (`PHIL_DEV_MYSQL_PORT`) | `PHIL_TEST_MYSQL_URL` | `mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4` |
| PostgreSQL 17 | `postgres:17-alpine` | 15432 (`PHIL_DEV_POSTGRESQL_PORT`) | `PHIL_TEST_POSTGRESQL_URL` | `postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17` |
| Gotenberg 9 | `gotenberg/gotenberg:9` | 3011 (`PHIL_DEV_GOTENBERG_PORT`) | `PHIL_GOTENBERG_URL` | `http://127.0.0.1:3011` |

Port 3999 stays free: a Mercure hub used by tests listens there. A variable already set is kept.

The file also holds `frankenphp` (`dunglas/frankenphp:1-php8.5`, port 8090, moved by `PHIL_DEV_FRANKENPHP_PORT`), under
the `bench` profile: `services up` leaves it out, and `phil-dev bench:worker` starts it with the worker script of a
package; see [Benchmarks](benchmarks.md#worker-mode).

## In a test

```php
$url = ServiceUrl::first('PHIL_EXAMPLE_TEST_DATABASE_URL', 'PHIL_TEST_POSTGRESQL_URL')
    ?? self::markTestSkipped('No PostgreSQL to test against.');
```

A package that needs a variable of its own — a database of its own, a timestamp authority — names it first, falls back
on the shared one, and documents it in its `doc/testing.md`.

The databases are tested against the containers only. The HTTP services — Gotenberg, a model provider, a geolocation
database — are recorded once against the containers or the real service and replayed offline: see
[HTTP recordings](http-recordings.md).
