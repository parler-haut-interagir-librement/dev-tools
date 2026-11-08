# The version matrix

Every package is tested on two versions of PHP and of Symfony, which `config/matrix.json` lists; `phil-dev matrix`
prints them. No script names a version: the PHP range of PHPStan, the images of `phil-dev php`, the Symfony version of
`check-packages --symfony` and the constraints of the skeleton all read this file.

```json
{
    "php": [
        {"version": "8.5", "status": "floor", "image": "php:8.5-cli"},
        {"version": "8.6", "status": "next", "image": "php:8.6-rc-cli"}
    ],
    "symfony": [
        {"version": "8.2", "status": "floor"}
    ]
}
```

## The policy

- **The floor** is the version the packages enforce: `"php": ">=8.5"`, `"symfony/…": "^8.2"`.
- **The next version** is added from its first release candidate, and supported before it is enforced: the constraints
  already allow it, and the tests, PHPStan and the standalone check run on it.
- **The floor is raised** only in a new release of the packages, once the next version reaches its x.y.3 release
  (PHP 8.6.3, Symfony 8.3.3). The old floor then leaves the matrix.

The matrix therefore holds one floor and at most one next version for each product.

## Adding a version at its RC1

1. Add it to `config/matrix.json` with `"status": "next"` and, for PHP, its image (`php:8.6-rc-cli`).
2. Build its image and run the tests: `vendor/bin/phil-dev php next --build`, then `composer test:next` and
   `composer packages:next` in a monorepo; for Symfony, `vendor/bin/phil-dev check-packages --symfony=next`.
3. Fix what fails on the next version without breaking the floor. A deprecation of the next version fails the tests.
4. Move the image to the release (`php:8.6-cli`) when the version is released.

## Enforcing it at its x.y.3

1. In a new release of the packages, raise the constraints: `"php": ">=8.6"`, `"symfony/…": "^8.3"`.
2. In `config/matrix.json`, give the version `"status": "floor"` and remove the old floor.
3. Run every gate; the skeleton and `init` write the new floor from then on.

## Running on a version

```sh
vendor/bin/phil-dev php next                                       # PHPUnit
vendor/bin/phil-dev php 8.6 -- php vendor/bin/phil-dev check-packages
vendor/bin/phil-dev php next -- php vendor/bin/phil-dev infection
vendor/bin/phil-dev check-packages --symfony=next
```

`phil-dev php` builds `resources/php/Dockerfile` from the image of the version — with intl, sodium, openssl, zip,
pdo_mysql, pdo_pgsql, pdo_sqlite, mbstring and gd with JPEG, PNG, WebP and AVIF, the `sqlite3` command, Composer, and
pcov, or Xdebug in coverage mode where pcov does not build yet — and runs the command with the project mounted at
`/app`. Every deprecation is reported and fails the tests, which run with `failOnDeprecation`. The project's `vendor/`
is shared with the host.

Each directory outside the project that a path repository of its `composer.json` names is mounted read-only where its
relative URL reaches from `/app` — `../../Phil/phil-prerelease/packages/*` at `/Phil/phil-prerelease/packages`, since
`..` stops at the container's root — or at its own absolute path: a monorepo that installs the packages of a
neighbouring one, and `phil/dev-tools` beside it, install and run in the container as on the host.

`check-packages --symfony` pins, in each temporary copy, the `symfony/*` requirements written at the floor to the version
chosen, and installs with release candidates allowed, so that a package is tested on the next Symfony without touching
its `composer.json`. A monorepo's own tests run with the root's installed Symfony; the standalone check is the run on
another Symfony.

Infection runs on the floor once per batch, on the files changed, and on the whole code on both versions weekly and
before each release: see [Quality gates](quality-gates.md#infection).
