# Commands

`phil-dev` runs from the root of a package or a monorepo; `PHIL_DEV_ROOT` names another root. Every command takes
`--skip-planned`, which leaves the planned packages out, as `PHIL_DEV_SKIP_PLANNED=1` does; see
[Configuration](configuration.md#planned-packages).

| Command | What it does |
|---|---|
| `init [--force] [--monorepo\|--package]` | Writes the thin configuration files and the Composer scripts. |
| `new <vendor/name> [--bundle] [--planned] [--requires=…] [--title=…] [--description=…] [--description-fr=…] [--dir=…] [--year=…]` | Writes the skeleton of a new package. |
| `check-docs [packages…] [--forbid=REGEX…]` | Checks the documentation of each package. |
| `check-translations [packages…]` | Compares the English documentation of each package with its French mirror. |
| `stamp-translations <packages>… [--file=PATH…]` | Records, in each French mirror, the English document it now follows. |
| `check-packages [packages…] [--symfony=VERSION] [--keep]` | Installs each package on its own, then runs its dependency analysis and its tests. |
| `infection [--changed[=REF]] [-- arguments]` | Mutation testing, of the whole code or of the files changed since a ref. |
| `deptrac` | Generates the Deptrac configuration and checks the layers. |
| `witnesses` | Checks that PHPStan still reports each forbidden call in its witness. |
| `generate` | Writes the generated Deptrac, Infection, Psalm, Biome and churn-php configurations under `var/phil-dev/`. |
| `test [--suite=NAME] [-- arguments]` | Runs the tests with ParaTest, or PHPUnit when ParaTest is missing. |
| `audit` | Checks the installed packages against the security advisories. |
| `unused` | Finds the required packages the code never uses. |
| `normalize [--fix]` | Checks, or fixes, that every `composer.json` is normalized. |
| `twig [--fix]` | Checks, or fixes, the code style of the Twig templates. |
| `twigstan` | Analyses the Twig templates with TwigStan. |
| `js [--fix]` | Checks, or fixes, the Stimulus controllers and other scripts with Biome. |
| `taint [-- arguments]` | Runs the taint analysis of Psalm on the production code. |
| `fuzz [targets…] [--budget=SECONDS] [--max-runs=N]` | Fuzzes the parsers the packages declare. |
| `sbom [packages…]` | Writes the CycloneDX bill of materials of each package. |
| `bc [-- arguments]` | Checks the public API against the latest tag. |
| `bench [--baseline\|--compare] [--threshold=PERCENT] [-- arguments]` | Runs the benchmarks, stores the baseline, or compares with it. |
| `bench:worker [package]` | Measures a page served by FrankenPHP in worker mode. |
| `split [packages…] [--push] [--branch=NAME] [--tag=TAG]` | Splits the finished packages to their public repositories. |
| `lint-docs [packages…]` | Lints the prose of the documentation with cspell, markdownlint and Vale. |
| `hotspots` | Lists the files that change most and are the most complex. |
| `services up\|down\|env\|test\|record [-- arguments]` | Starts, stops and points the tests at MySQL, PostgreSQL and Gotenberg. |
| `php <version> [--build] [-- command]` | Runs a command on a PHP version of the matrix, in its Docker image. |
| `matrix` | Lists the versions of PHP and Symfony the packages are tested on. |

## init

Writes, if missing: `phil-dev.php`, `.php-cs-fixer.dist.php`, `rector.php`, `arkitect.php`, `phpstan.neon`,
`phpunit.dist.xml`, `phpbench.json`, `captainhook.json`, `composer-dependency-analyser.php` (for a package) and
`vendor-bin/<tool>/composer.json` for each tool: PHP-CS-Fixer, PHPStan, Rector, Deptrac, Arkitect, Infection, PHPBench,
Psalm, php-fuzzer, the CycloneDX plugin, the backward-compatibility checker, TwigStan and Twig-CS-Fixer, Biome,
composer-unused, composer-normalize, CaptainHook and churn-php. Each thin file calls the shared one of this package
with its own directory; see [Configuration](configuration.md).

It adds to `composer.json` the scripts, their descriptions, `bamarni/composer-bin-plugin`, `brianium/paratest`,
`ergebnis/phpunit-slow-test-detector`, `phpunit/phpunit` and `shipmonk/composer-dependency-analyser` in `require-dev`,
the plugin's settings, `config.policy.advisories.block: true` and `config.process-timeout: 0`. The scripts it adds go at the end of `scripts`: run
`composer deps:normalize:fix` afterwards, as the `deps:normalize` gate sorts them. A file
or a value already identical is left alone; one that differs is kept and reported, and `--force` replaces it. The
`phpunit.dist.xml` lists the test directories that exist when it is written, and the `packages/*` globs in a monorepo;
it requires the coverage metadata of every test, registers the slow-test detector, and the `SymfonyExtension` of
`symfony/phpunit-bridge` when the bridge is installed.

## new

`new phil/example` writes `packages/example` in a monorepo, or `example` elsewhere; `new tilttek/example` writes
`packages/tilttek-example`. `--bundle` writes a bundle and adds `-bundle` to the name when it is missing; a name that
ends with `-bundle` is a bundle without the option. Only the `phil` and `tilttek` vendors are accepted: the wording of
each is in the templates. A directory that exists and is not empty is refused.

| Option | Effect |
|---|---|
| `--description` | What the package does: the description of `composer.json` and the README. |
| `--description-fr` | The French description, for `README.fr.md`; the English one by default. |
| `--title` | The title of the README and of its mirror; the package name by default. |
| `--planned` | A package being written: `'status' => 'planned'`, a README that describes what it will do, and a smoke test. The first sentence of the description is its summary. |
| `--requires` | The packages it requires, comma-separated: `--requires=phil/geo,tilttek/dice`, each at `^0.1@dev` unless a constraint follows a colon, as in `symfony/uid:^8.2`. |

```sh
vendor/bin/phil-dev new tilttek/cards --planned --requires=tilttek/dice --title="Cards" \
    --description="Decks of cards. Shuffled, dealt and drawn, with the history of each draw." \
    --description-fr="Des paquets de cartes. Mêlés, distribués et piochés, avec l'historique de chaque pioche."
```

## check-docs

Runs the checks of [The package skeleton](skeleton.md#the-documentation-checks) on each member of a monorepo, or on
the package at the root; `PHIL_DEV_PACKAGES` or the arguments narrow the members. The terms no package names are the
regular expressions of the root's `docs.forbidden` and of each `--forbid`.

## check-translations, stamp-translations

`check-translations` compares `README.md` and `doc/**/*.md` with `README.fr.md` and `doc/fr/**`: a document without its
mirror, a mirror without its document, the number of headings of each level and the code blocks fail; a translation
behind its English is reported. A package whose `docs.translations`, else the root's, is `advisory` never fails, and is
summed up on one line; `-v` lists its findings. `stamp-translations` writes the SHA-256 of the English on the first line
of each mirror of the packages it names; `--file` narrows it to documents given by their English or French path. See
[Quality gates](quality-gates.md#the-french-mirror-of-the-documentation).

## check-packages

The gates of a monorepo cover the code of its packages, not their `composer.json`: the root's dependencies hide a
`require` a package forgot. This command copies each package into a temporary directory, leaving out `vendor/`,
`var/` and `composer.lock`, gives the copy a path repository on each glob of the root's `packages` — copied, not
linked —, installs it, runs `composer-dependency-analyser` with the package's own configuration, then PHPUnit with the
package's own configuration when it has one, else `phpunit --no-configuration tests`. A failure is reported and the
next package is checked. `--keep` keeps the copies.

The path repositories install each member at the branch its branch alias names (`dev-main`, aliased `0.1.x-dev`),
whatever branch the checkout is on: from a worktree on `b/topic`, Composer would otherwise read `dev-b/topic`, which no
alias covers and no `^0.1@dev` of a sibling accepts. Each step runs with `TMPDIR` inside the copy, so that its test
kernels never share a cache with another checkout's.

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
`generate` only writes the generated configurations, to read them.

## test

Runs the suites of `phpunit.dist.xml` with ParaTest, one process per core, when `vendor/bin/paratest` exists; else with
PHPUnit. The root's `'phpunit' => ['parallel' => false]` keeps PHPUnit, for tests that share a resource they cannot
share at once. `--suite` runs one suite; arguments after `--` go to the runner. Infection always replays the Unit suite
with PHPUnit. While the planned packages are skipped, `test` and `infection` read `var/phil-dev/phpunit/phpunit.xml`,
the root's configuration without them.

`test`, `infection` and `services test|record` run with `TMPDIR` under `var/tmp/` of the root, unless it already points
inside the root: the test kernels that keep their cache under the system's temporary directory get one per checkout,
and a clone and its worktrees can run their tests at the same time.

## audit, unused, normalize, twig, js

The gates added to `composer gates`, each turned off by `'gates' => ['<name>' => false]` in the root's `phil-dev.php`:
see [Quality gates](quality-gates.md). `normalize --fix`, `twig --fix` and `js --fix` apply the fixes.

## taint, fuzz, sbom

See [Security checks](security.md).

## twigstan, bc

`twigstan` writes `var/phil-dev/twigstan.php` and its PHPStan configuration, then runs TwigStan: see
[Quality gates](quality-gates.md#twig-templates). `bc` compares the public API with the latest tag of the root's
`bc.tags` glob, every tag by default; a repository without a tag passes, and a monorepo leaves the check to the split
repositories of its members, where their tags are.

## bench, bench:worker

See [Benchmarks](benchmarks.md).

## split, lint-docs, hotspots

See [The monorepo workflow](workflow.md).

## services, php, matrix

See [Real servers](services.md), [HTTP recordings](http-recordings.md) and [The version matrix](version-matrix.md).
