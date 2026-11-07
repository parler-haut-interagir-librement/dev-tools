# Commands

`phil-dev` runs from the root of a package or a monorepo; `PHIL_DEV_ROOT` names another root.

| Command | What it does |
|---|---|
| `init [--force] [--monorepo\|--package]` | Writes the thin configuration files and the Composer scripts. |
| `new <vendor/name> [--bundle] [--description=…] [--dir=…] [--year=…]` | Writes the skeleton of a new package. |
| `check-docs [packages…] [--forbid=REGEX…]` | Checks the documentation of each package. |
| `check-packages [packages…] [--symfony=VERSION] [--keep]` | Installs each package on its own, then runs its dependency analysis and its tests. |
| `infection [--changed[=REF]] [-- arguments]` | Mutation testing, of the whole code or of the files changed since a ref. |
| `deptrac` | Generates the Deptrac configuration and checks the layers. |
| `witnesses` | Checks that PHPStan still reports each forbidden call in its witness. |
| `generate` | Writes the generated Deptrac and Infection configurations under `var/phil-dev/`. |
| `services up\|down\|env\|test\|record [-- arguments]` | Starts, stops and points the tests at MySQL, PostgreSQL and Gotenberg. |
| `php <version> [--build] [-- command]` | Runs a command on a PHP version of the matrix, in its Docker image. |
| `matrix` | Lists the versions of PHP and Symfony the packages are tested on. |

## init

Writes, if missing: `phil-dev.php`, `.php-cs-fixer.dist.php`, `rector.php`, `arkitect.php`, `phpstan.neon`,
`phpunit.dist.xml`, `phpbench.json`, `composer-dependency-analyser.php` (for a package) and
`vendor-bin/<tool>/composer.json` for PHP-CS-Fixer, PHPStan, Rector, Deptrac, Arkitect, Infection and PHPBench. Each
thin file calls the shared one of this package with its own directory; see [Configuration](configuration.md).

It adds to `composer.json` the scripts, their descriptions, `bamarni/composer-bin-plugin`, `phpunit/phpunit` and
`shipmonk/composer-dependency-analyser` in `require-dev`, the plugin's settings and `config.process-timeout: 0`. A file
or a value already identical is left alone; one that differs is kept and reported, and `--force` replaces it. The
`phpunit.dist.xml` lists the test directories that exist when it is written, and the `packages/*` globs in a monorepo;
it registers the `SymfonyExtension` of `symfony/phpunit-bridge` when the bridge is installed.

## new

`new phil/example` writes `packages/example` in a monorepo, or `example` elsewhere; `new tilttek/example` writes
`packages/tilttek-example`. `--bundle` writes a bundle and adds `-bundle` to the name when it is missing; a name that
ends with `-bundle` is a bundle without the option. Only the `phil` and `tilttek` vendors are accepted: the wording of
each is in the templates. A directory that exists and is not empty is refused.

## check-docs

Runs the checks of [The package skeleton](skeleton.md#the-documentation-checks) on each member of a monorepo, or on
the package at the root; `PHIL_DEV_PACKAGES` or the arguments narrow the members. The terms no package names are the
regular expressions of the root's `docs.forbidden` and of each `--forbid`.

## check-packages

The gates of a monorepo cover the code of its packages, not their `composer.json`: the root's dependencies hide a
`require` a package forgot. This command copies each package into a temporary directory, leaving out `vendor/`,
`var/` and `composer.lock`, gives the copy a path repository on each glob of the root's `packages` — copied, not
linked —, installs it, runs `composer-dependency-analyser` with the package's own configuration, then PHPUnit with the
package's own configuration when it has one, else `phpunit --no-configuration tests`. A failure is reported and the next package is checked. `--keep` keeps the copies.

`--symfony=VERSION` (a version of the matrix, `floor` or `next`) pins, in the copy, every `symfony/*` requirement
written at the floor (`^8.2`) to `VERSION.*` and lets release candidates in: the package is tested on that Symfony
without touching its `composer.json`. See [The version matrix](version-matrix.md).

## infection

Generates `var/phil-dev/infection.json` — see [Quality gates](quality-gates.md#infection) — and runs Infection on the
Unit suite with all threads. `--changed` mutates only the PHP files added or modified since the point where the
branch left the base ref, committed or not: the ref given, else `PHIL_DEV_BASE_REF`, else `origin/main` when it
exists, else `main`. Nothing changed passes. Arguments after `--` go to Infection.

## deptrac, witnesses, generate

`deptrac` writes `var/phil-dev/deptrac.yaml` (see [Dependencies](dependencies.md)), runs Deptrac on it with
`--fail-on-uncovered --report-uncovered`, then on each file of the root's `deptrac.configs`. `witnesses` runs PHPStan
on every `tests/Witnesses/PhpStan` directory with the forbidden calls only; see [Quality gates](quality-gates.md#phpstan).
`generate` only writes the two generated configurations, to read them.

## services, php, matrix

See [Real servers](services.md), [HTTP recordings](http-recordings.md) and [The version matrix](version-matrix.md).
