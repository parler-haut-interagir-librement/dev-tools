# Testing

## This package's tests

```sh
composer install
composer portes
```

`phil-dev` is checked by its own configurations: `phil-dev.php` at the root lists `src`, `tests`, `config` and
`bin/phil-dev`, and `init` wrote its thin files. The unit suite covers every class and command, with the processes
recorded instead of run; the skeleton and the files `init` writes are compared with the golden copies of
`tests/Fixtures/`. The witnesses of `tests/Witnesses/PhpStan` prove each forbidden call is reported, and Infection holds
the code at 100 % of mutants killed.

On the next PHP of the matrix:

```sh
php bin/phil-dev php next
php bin/phil-dev php next -- php bin/phil-dev infection
```

## Testing with the helpers

`Phil\DevTools\Testing\ServiceUrl::first()` reads the URL of a real server for an integration test skipped without one,
and `Phil\DevTools\Testing\HttpRecording` records and replays the exchanges of an HTTP service: see
[Real servers](services.md) and [HTTP recordings](http-recordings.md). A package that uses them requires
`phil/dev-tools` in `require-dev`, with `symfony/http-client` and `symfony/phpunit-bridge` for the recordings.
