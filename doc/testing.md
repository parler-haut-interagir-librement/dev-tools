# Testing

## This package's tests

```sh
composer install
composer gates
```

`phil-dev` is checked by its own configurations: `phil-dev.php` at the root lists `src`, `tests`, `config` and
`bin/phil-dev`, and `init` wrote its thin files. The unit suite covers every class and command, with the processes
recorded instead of run, the programs of the `PATH` stood in by empty executables, and the built-in server of PHP
standing in for FrankenPHP; `merge-lot` runs with git itself on throwaway repositories, Composer stood in by a PHP script; the skeleton and the files `init` writes are compared with the golden copies of
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
[Real servers](services.md) and [HTTP recordings](http-recordings.md). `RouteSmokeTesting` requests every GET route
without a parameter of a test kernel: see [below](#route-smoke-test). A package that uses them requires
`phil/dev-tools` in `require-dev`, with `symfony/http-client` and `symfony/phpunit-bridge` for the recordings.

## Route smoke test

`Phil\DevTools\Testing\RouteSmokeTesting` is a trait for a `WebTestCase`: every GET route without a parameter of the
test kernel is requested, and fails the test when it answers a server error (a status of 500 or more). A bundle uses it
on the small application it boots for its functional tests, an application on its own kernel:

```php
use Phil\DevTools\Testing\RouteSmokeTesting;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RouteSmokeTest extends WebTestCase
{
    use RouteSmokeTesting;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @return list<array<string, string>>
     */
    protected static function smokeRouteParameters(): array
    {
        return [['_locale' => 'fr'], ['_locale' => 'en']];
    }
}
```

A route is requested when it accepts GET, or any method, and every parameter it has no default for is supplied by a set
of `smokeRouteParameters()`: once with no parameter, or once per set that supplies them all and whose values meet the
requirements of the route. A route whose name starts with a prefix of `smokeRouteExclusions()` is left out; by default
`_`, the profiler, the toolbar and the error pages. Each path is requested once, by a test named `GET <path>`, and a
kernel with nothing to request is an error rather than an empty pass.

`Phil\DevTools\Testing\SmokeRoutes::from()` makes that list from a route collection and a URL generator, for a test
that requests the routes another way. The trait needs `symfony/framework-bundle` and `symfony/browser-kit`, and
`SmokeRoutes` needs `symfony/routing`, all three in the `require-dev` of the package that uses them.
