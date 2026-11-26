# Changelog

All notable changes to `phil/dev-tools` will be documented in this file.

Updates follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles, and versions follow
[Semantic Versioning](https://semver.org/).

## Unreleased

The next version is 0.2.1-alpha.

### Added

- `resources/castor/tasks.php`, the common Castor tasks of an application, imported by its `castor.php` from
  `vendor/phil/dev-tools/`: the tests by suite (`test:all`, `test:suite`, `test:unit`, `test:integration`,
  `test:functional`, `test:api`, `test:e2e`, `test:next`), `test:coverage` with its threshold of covered lines,
  `test:infection`, `security:taint`, the lints and the static analysis (`lint:*`, `lint:analyse`), the fixers
  (`fix:*`), `ci:all`, `helpers:hooks` and `helpers:versions`. The constant `PHIL_CASTOR` sets the suites, the
  threshold, the YAML paths, the templates, the dev container and the application's own functions each aggregate task
  calls after its steps; `Castor\Settings` reads it, `Castor\Commands` builds the commands and reads the coverage
  summary, and `Exception\InvalidCastorSettings` refuses an unknown key or a wrong value. See `doc/castor.md`.
- `Testing\RouteSmokeTesting`, a trait for the `WebTestCase` of a bundle's test kernel or of an application: every GET
  route without a parameter, or whose parameters a set of `smokeRouteParameters()` supplies, answers under 500; the
  routes named `_…` are left out by default (`smokeRouteExclusions()`), and a kernel with nothing to request is an
  error. `Testing\SmokeRoutes` makes the list of requests from a route collection. `symfony/framework-bundle`,
  `symfony/browser-kit` and `symfony/routing` are suggested.
- The optional integrations of a bundle in `phil-dev deptrac`: a bundle with an `Integration` directory, or whose
  manifest sets `deptrac.integrations`, gets a `<Name>Integration` layer (`LayerKind::Integration`). The packages of
  its `suggest` that it does not require are visible to that layer and to its bundle class only — a known package
  through its own layers, an installed one through a `Suggested…` layer per PSR-4 namespace read from
  `vendor/<name>/composer.json`, taken out of `Forbidden` and seen by every other layer that sees `Forbidden`; its
  domain, application and infrastructure see neither, `require-dev` included, and no other package sees the
  integration. A namespace the default or an `allow` covers stays there. `Config\Integrations` decides both;
  `Package::$suggests` holds the names of `suggest`, `Layer::seenByOthers()` says whether another package may see a
  layer. See `doc/dependencies.md`.
- `phil-dev hooks [--check]`: installs the hooks of `captainhook.json` with `--git-directory` set to the git directory
  the clone and its worktrees share, after checking that the checkout has CaptainHook (`config.run.path`) and the tool
  under `vendor-bin/` of each `staged <tool>` action, naming the `composer bin <tool> install` of each one missing;
  `--check` stops after the check. `doc/workflow.md` describes the hooks of a worktree and the `captainhook.json` of a
  monorepo, which a test keeps equal to the generated one.

### Changed

- The `hooks` script `init` writes runs `phil-dev hooks`, and the Castor task `helpers:hooks` passes CaptainHook the
  shared git directory: run from a worktree, CaptainHook no longer writes the hooks beside a `.git` file.
- `Layer::seenByApplications()` is false for an integration. `StagedCommand::TOOLS` is public, for `phil-dev hooks`.

## 0.2.0-alpha - 2026-11-25

Its new rules fail the packages that break them.

### Added

- `phil.parseUrl` in `config/phpstan-disallowed.neon`: `parse_url()` and the constant `FILTER_VALIDATE_URL` are
  refused, with a message that points to `phil/url` and its WHATWG URL parsing; allowed in the test suites. Its
  witnesses are `tests/Witnesses/PhpStan/ParseUrl.php` and `FilterValidateUrl.php`.
- `phil.processState` in `config/phpstan-disallowed.neon`: `setlocale()`, `putenv()` and `date_default_timezone_set()`
  are refused, since what they set outlives the request in a worker; allowed in the test suites. Its witnesses are
  `SetLocale.php`, `PutEnv.php` and `DefaultTimezone.php`.
- `DoctrineMappingTypeRule`, loaded by `config/phpstan.neon`: a Doctrine mapping with a mutable date type
  (`Types::DATE_MUTABLE`, `DATETIME_MUTABLE`, `DATETIMETZ_MUTABLE`, `TIME_MUTABLE` or their names) or a mapped
  `\DateTime` property is reported as `phil.doctrineMutableDate`, a `decimal` column as `phil.doctrineDecimal`.
- `HmacSecretKeyRule`, loaded by `config/phpstan.neon`: the application secret used as it is as the key of
  `hash_hmac()`, `hash_hmac_file()`, `hash_init()`, `sodium_crypto_auth()`, `sodium_crypto_auth_verify()` or
  `sodium_crypto_generichash()` — the literal `%kernel.secret%`, `getParameter('kernel.secret')`, or a variable, a
  property or an array key named `secret`, `kernelSecret`, `appSecret` or `applicationSecret` — is reported as
  `phil.hmacSecretKey`: each use derives its own key with `hash_hkdf()`.
- `ChainedAssignmentRule`, loaded by `config/phpstan.neon`: a chained assignment of a `new`, or one that writes a
  property with a `set` hook, is reported as `phil.chainedAssignment`, once per chain.
- `InheritedPropertyRedeclarationRule`, loaded by `config/phpstan.neon`: a property, declared or promoted, that a parent
  declares `private` or `static` is reported as `phil.propertyRedeclaration`.
- `ArrayMergeInLoopRule`, loaded by `config/phpstan.neon`: `array_merge()` or `array_merge_recursive()` of an array into
  itself in a `for`, `foreach`, `while` or `do`, outside the closures and functions inside it, is reported as
  `phil.arrayMergeInLoop`.
- Each new rule has its witness under `tests/Witnesses/PhpStan` and its accepted and refused fixtures under
  `tests/Fixtures/phpstan`. A witness whose docblock announces `none` must pass: `phil-dev witnesses` fails when PHPStan
  reports anything there; `Accepted.php` is the one of this package.
- `phil-dev check-config [packages…] [--strict]`, and the `conventions` script of `init`: it warns when a bundle's root
  key is not `<vendor>_<name>`, when an option has no default and is not required, when an option is not named in
  `doc/*.md` or `README.md`, and when the latest recipe under `recipe/<version>/config/packages/` sets an option to its
  default. The tree is read from the source without loading it. The warnings fail nothing; `--strict` fails.
  `check-packages` prints them with its other warnings.
- `Phil\DevTools\Fuzz\RegexGenerator`: strings a regular expression accepts, drawn with a fixed seed (character
  classes, POSIX classes, a few Unicode properties, escapes, groups, alternatives, quantifiers, anchors), each checked
  against the expression; lookarounds, back-references, atomic groups, possessive quantifiers, word boundaries, inline
  flags and the `x` modifier are refused with `UnsupportedPattern`. It takes rejected cases written by hand and throws
  `RejectedCaseAccepted` when none is given or one is accepted. It is a fuzz target of this package,
  `regex-generator`.
- `phil-dev taint` loads the Doctrine plugin of Psalm when the workspace installs `doctrine/dbal`, `doctrine/orm`,
  `doctrine/persistence` or `doctrine/collections`, and sets `markAllNonInternalCodeAsApi` and `loadLibraryPlugins`. An
  integration test proves that a request concatenated into a query is reported as `TaintedSql`, and a stored value
  printed back unescaped as `TaintedHtml`.
- `phil-dev merge-lot <branch> [--main=NAME] [--lot=NAME]`: the whole merge of a lot's branch into main, from the
  checkout of main, stopping at the first step that fails with an exit code of its own (2 nothing to merge, 3 locked,
  4 tracked files modified, 5 rebase, 6 `composer install`, 7 gate, 8 merge, 9 merge commit, 10 cleanup). It takes the
  lock `.git/fusion-lot.lock`, holding the PID, the start time, the lot and the branch, and takes it over with a
  message when `ps` finds its process gone or when it has not moved for 30 minutes; checks that no tracked file is
  modified in the checkout of main or in the worktree; rebases the branch onto main in its worktree, then runs
  `composer install` there when a `composer.lock` changed; runs each script of the worktree's `gates` script but
  Infection, the full PHPStan among them; merges with `git merge --no-ff` and the message « Fusion du lot <lot> », one
  bullet per commit; checks the merge commit's parents and subject; runs `composer install` in the checkout of main
  when the merge changed a `composer.lock`; and only then removes the worktree and deletes the branch with
  `git branch -d`. A failed rebase or merge is aborted, nothing is removed, and the lock is released. A merge of the
  same lot whose lock is taken over is run again, its unfinished `git merge` aborted; a branch already merged by its
  merge commit only has its worktree and branch removed. The classes are `Merge\MergeLock`, `LockHolder`,
  `LockTakeover`, `MergeMessage`, `Worktrees` and `MergeFailure`, and the exception `MergeLockHeld`. The lock and the
  worktrees git lists are fuzzed by the `merge-lock` target.

### Changed

- Infection is required as `^0.35` by `vendor-bin/infection/composer.json` and by the one `phil-dev init` writes, up
  from `^0.34`. The generated `var/phil-dev/infection.json` is unchanged and valid against the 0.35 schema, and
  `--threads=max` starts one process per core but one. An argument given after `--` that is not a readable path, such
  as `Differ::diff`, reaches Infection as a source symbol selector.
- `vendor-bin/psalm` and the one `init` writes require `vimeo/psalm` `^7.15`, which follows SQL from what a query
  stores to what another reads back.
- `vendor-bin/rector` and the one `init` writes require `rector/rector` `^2.9.2`; `config/rector.php` calls
  `reportUnusedSkips()`, which lists, at the end of a run, the skips that matched nothing, without failing it.
- The `phpunit.dist.xml` that `init` writes, and this package's, set `failOnRisky="true"`: a risky test fails the run.
- `ext-ctype` is required: the regex generator reads character classes with `ctype_alnum()`, `ctype_alpha()` and
  `ctype_xdigit()`.

### Fixed

- `infection --changed` passes the changed files after `--`. Placed right after `--ignore-msi-with-no-mutations`,
  whose value is optional, the first file was taken as the option's value: with one changed file, Infection mutated
  every source directory of the packages analysed, and with several, the first file was left out.
- The generated `var/phil-dev/biome.json` enables the recommended rules with `linter.rules.preset: "recommended"`,
  the form of Biome 2.6: `phil-dev js` no longer prints the deprecation of `linter.rules.recommended`.
- `config/rector.php` no longer passes `instanceOf` and `earlyReturn` to `withPreparedSets()`: their rules belong to
  the `codeQuality` and `codingStyle` sets it already enables, and Rector no longer warns at the end of each run.
- `config/rector.php` no longer skips `InitializeCollectionInConstructorRector` and
  `AttributeKeyToClassConstFetchRector` in `Domain` layers: no set of Rector 2.9 registers them, and
  `reportUnusedSkips()` no longer lists them as unused.

## 0.1.8-alpha - 2026-11-24

### Added

- `PsrLogContextRule`, loaded by the shared `config/phpstan.neon`: a PSR-3 logger call whose message is built by
  interpolation, concatenation, `sprintf()` or `vsprintf()` is reported as `phil.psrLogMessage`, unless PHPStan knows
  the message to be a literal; an object other than a `Throwable` in its context, down to four levels of nested arrays,
  as `phil.psrLogContext`. Its witnesses are `tests/Witnesses/PhpStan/LogMessage.php` and `LogContext.php`.
- `check-packages` expects unused the polyfills of the running PHP version and of the earlier ones, such as
  `symfony/polyfill-php86` on PHP 8.6, which polyfill nothing there: a configuration written in the copy loads the
  package's own and ignores their unused-dependency error, so a package that requires one for PHP 8.5 passes the
  analysis on PHP 8.6.
- `phil-dev php` mounts, from a git worktree, the worktree's repository: the directory its `commondir` names, read-only
  through `--mount`, at the path the `.git` file names — or where a relative one reaches from `/app` —, with `/app`
  declared safe to git by `GIT_CONFIG_COUNT`, `GIT_CONFIG_KEY_0=safe.directory` and `GIT_CONFIG_VALUE_0=/app` in the
  container's environment. `infection --changed` lists the changed files in the container from a worktree as from the
  main checkout. A `.git` file that cannot be read, or that names anything but a directory under the `worktrees/` of a
  repository, is reported on the error output and mounts nothing; an ordinary checkout mounts nothing more.

### Changed

- The image of `phil-dev php` builds liblouis 3.39.0 from its released tag, checked against the commit the Dockerfile
  pins, in a stage of its own, instead of installing the older Debian package: `lou_translate` and the tables are
  under `/usr/local`, and the build fails when `lou_translate --version` reports a version older than 3.39.0 or the
  tables are missing.

### Removed

- `CheckRequiredInterfaceInContractNamespaceRule` of `symplify/phpstan-rules` is no longer loaded by
  `config/phpstan-symplify.php`: an interface stays beside its adapters or in a `Contract` namespace, as its code reads
  best. An exception a `phil-dev.php` still declares for `symplify.requiredInterfaceContractNamespace` is given
  `reportUnmatched: false`, so it fails no analysis until the package drops it.

### Fixed

- The `.github/dependabot.yml` that `phil-dev split --security-files` writes in a split repository gives each update
  a seven-day `cooldown`, as the monorepo's does: zizmor 1.32 fails the job of a Dependabot configuration without one.
- `infection --changed` passes each changed file to Infection as an absolute path of its own: Infection resolves a
  relative path against the directory of the generated configuration, under `var/phil-dev/`, and stopped with
  `multiple paths must be passed as separate arguments`.
- `infection --changed` passes only the changed files inside the source directories Infection mutates: Infection
  reads any other existing path as a test path for PHPUnit, so a file of a planned package that is skipped, or outside
  the `infection.directories` of its package, reached PHPUnit. When no changed file is left, nothing is mutated and
  the command passes.
- `infection --changed` fails when git cannot list the changed files — a ref it does not know, or a worktree whose
  repository is out of reach, as in a container that mounts the worktree alone — instead of passing with nothing to
  mutate.
- The shared PHP-CS-Fixer configuration runs on a PHP newer than the syntax PHP-CS-Fixer declares, so that the code
  style and the commit hooks run on the next PHP of the matrix; the code keeps to the syntax of the floor.

## 0.1.7-alpha - 2026-11-20

### Added

- `infection.memoryLimit` in the root's `phil-dev.php`, the memory limit of Infection and of its initial test run, a
  `php.ini` limit such as `4G`, `512M` or `-1`; `4G` by default.
- The `js` gate runs, after Biome and unless `--fix`, the script tests of each package analysed that has a `tests/js`
  directory: `node --test 'tests/js/**/*.test.mjs'` from the package's directory, after
  `--import ./tests/js/register.mjs` when the package has that file. A workspace with script tests and without `node`
  on the `PATH` fails.
- `check-packages` fails a package whose Composer archive leaves out what it must keep — the paths of its `autoload`
  and `autoload-dev`, its `LICENSE` and `README.md` — through a pattern of its `.gitattributes` (`export-ignore`) or
  of its `archive.exclude`, and names the pattern. `Split\ArchiveRules` reads those patterns as git
  does; it is fuzzed by the `archive-rules` target.
- `phil-dev taint` follows the custom taint `request_host`: the host a request names, through `Request::getHost()`,
  `getSchemeAndHttpHost()`, `getUri()`, the `Host` and `X-Forwarded-Host` headers, their server variables and the
  absolute addresses of the router, to the body, the template context or the action of an email, reported as
  `TaintedCustom`. The model is `resources/psalm/request-host.phpstub`, named by the generated `psalm.xml`.
- `twigstan --strict`, which returns TwigStan's own exit code.
- `pcntl` in the image of `phil-dev php`, for the tests that send a worker a signal.
- The installation note for Symfony 8.2 while it is in BETA: `minimum-stability: beta` with `prefer-stable: true`.

### Changed

- `infection --changed` runs, in a monorepo, only the tests of the packages that hold the changed files, and covers
  only their code: the other members are left out of the generated PHPUnit configuration, as the skipped planned
  packages are. It names those packages on a line of its own.
- `twigstan` is advisory: it passes whatever TwigStan finds, with a line that gives TwigStan's exit code. The shared
  Twig loader lacks the Twig extensions of the bundles, so the analysis of their templates stops short of a verdict.
- `phil-dev php` builds the image again when `resources/php/Dockerfile` changed since the image was built: the image
  carries the SHA-256 of its Dockerfile in its label `phil-dev.recipe`.
- The next PHP of the matrix, 8.6, is built from its release image, `php:8.6-cli`, instead of `php:8.6-rc-cli`; it
  stays `next`, the floor being raised at PHP 8.6.3.

### Security

- The image of `phil-dev php` builds its coverage driver from a released tag, checked against a pinned commit, instead
  of the default branch: pcov `v1.0.9` (`a2f930e98c9f5380af82f218916427b3a61f6e81`) and, where pcov does not build,
  Xdebug `3.6.0alpha1` (`653e0443683790877b99e4e7a8adedf84b8e298b`). A moved tag fails the build.

## 0.1.6-alpha - 2026-11-18

### Added

- `check-docs` refuses, in a table row of the Markdown files it checks — a line that starts and ends with `|` —, a code
  span holding a pipe without a backslash before it (`table-code-pipe`): on GitHub as in phil/markup, `` `a|b` `` splits
  the cell, and is written `` `a\|b` ``. Fenced code blocks are left out; code spans of several backticks are read as
  in CommonMark.

## 0.1.5-alpha - 2026-11-17

### Added

- `phil-dev staged php-cs-fixer|phpstan|twig-cs-fixer [--in-dir=DIR…]`, the fast gates of the pre-commit hook on the
  staged files: the names are read from `git diff --cached -z` and handed to the tool as arguments of their own, after
  `--`, never through a shell. PHPStan given only files its configuration excludes ((`No files found to analyse.`))
  passes.
- `ProcessRunner::capture()` takes `$withErrors`, which captures the error output with the standard one, in the order
  the command writes them.
- `phil-dev lsp`, the gate of `symfony lsp:check` (Symfony Language Tools) on each Symfony application — a member of
  type `project` with a `bin/console`, or such a root —, every code it lists given to `--fail-on`, without a baseline,
  in the `github` format under GitHub Actions; a bundle or a library is skipped with a note. `init` adds the `lsp`
  script and puts it in `composer gates`, after `lint:apps`; the root's `gates.lsp: false` turns it off.
- `.github/workflows/zizmor.yml` in the security commit of each split repository: zizmor audits its workflows at
  each push to its branch, each pull request and each week, its findings failing the job.
- `split --security-files`, at the root of a monorepo, adds `.github/dependabot.yml` — the `composer` dependencies of
  the root and of `vendor-bin/*`, the `npm` ones of the root and of the members, the `github-actions`, each release
  waiting a `cooldown` of seven days, the updates grouped — and `.github/workflows/zizmor.yml`, when they are missing.
- `README.fr.md`, the French README.

### Changed

- The `captainhook.json` `init` writes calls `phil-dev staged` instead of naming the staged files through CaptainHook's
  `{$STAGED_FILES}`, which joined them unquoted into a shell command: a staged file named `a; command;.php` ran that
  command on the next commit. Run `phil-dev init --force`, or replace the three actions by hand, in each project.
- `infection` run from the directory of a member of a monorepo, without an Infection of its own under `vendor-bin/`,
  runs from the monorepo's root on that member alone.
- `phil-dev php` also gives the container its own `var/tmp`, the `TMPDIR` of the tests, and `var/infection/tmp`, under
  `var/phil-dev/php/<version>/`: the test kernels the host compiled hold the host's paths.

### Fixed

- The PHPUnit configuration generated under `var/phil-dev/phpunit/` writes `noNamespaceSchemaLocation` as a path
  relative to itself, or keeps the URL the root wrote: Infection resolves a schema path that is not a URL against the
  directory of the configuration, even an absolute one, and stopped on `Invalid schema path found` before mutating.

## 0.1.4-alpha - 2026-11-15

### Added

- A Valkey 9 server in `resources/compose.yaml` (`valkey/valkey:9-alpine`, port 16379 moved by
  `PHIL_DEV_VALKEY_PORT`, without persistence), and `PHIL_TEST_VALKEY_URL` in `phil-dev services env|test|record`:
  `redis://127.0.0.1:16379/15`, database 15 for the tests.
- `check-packages` gives each copy the root's other path repositories too — a neighbouring monorepo whose packages
  the members require —, their URL made absolute and copied.
- `bench:worker` mounts the root's other path repositories read-only where the links of `vendor/` lead inside the
  container — `../public/packages/*` at `/public/packages` —, through a second Compose file under
  `var/phil-dev/bench/`; one whose place would hide a directory of the image is left out with a note.

### Changed

- `test` and `infection` read `var/phil-dev/phpunit/phpunit.xml` whenever the root's PHPUnit configuration holds a
  glob: each glob, such as `packages/*/src`, is replaced by the sorted list of directories it matches, so that PHPUnit
  and Infection read explicit directories wherever the checkout lies. `PhpUnitConfig::withoutSkipped()` is
  `PhpUnitConfig::generated()`.
- The shared PHPStan configuration gives each worker 30 minutes (`parallel.processTimeout: 1800.0`) instead of 10: the
  whole of a large monorepo analysed on PHP 8.6 by `phil-dev php next`, in Docker through a bind mount, stopped on an
  internal error when a worker outlasted the default.
- `infection --changed` counts the renamed files (`--diff-filter=AMR`) and the untracked files git does not ignore,
  sorted, and takes as its base, without a ref or `PHIL_DEV_BASE_REF`, whichever of `origin/main` and `main` the branch
  left last: the merges of a local `main` not pushed yet no longer count as changes of the branch.

### Fixed

- `phil-dev php` gives the container PHPStan temporary directories of its own, under `var/phil-dev/php/<version>/`, in
  place of the `tmpDir` of the root's `phpstan.neon` (or `phpstan.dist.neon`, `var/phpstan` otherwise) and of
  `var/phpstan-witnesses`: PHPStan keys its reflection cache by the PHP version analysed for and stores the path of the
  stubs inside its phar, so a cache the host wrote stopped the analysis in Docker with an internal error on a stub
  that "is not a file".

## 0.1.3-alpha - 2026-11-14

### Added

- `phil-dev lint-apps` and the `lint:apps` script, in the gates after Arkitect: each kernel under `tests/Functional/`
  of a package boots in a process of its own and runs `lint:container`, `lint:expressions` and, on the YAML files of
  its `config/` directory, `lint:yaml --check-schema` through its own console. A command the application does not
  have is skipped with a note, `lint:yaml` checks the syntax only without `opis/json-schema`, and a package leaves a
  kernel out under `apps.skip` of its `phil-dev.php`; `'gates' => ['apps' => false]` turns the gate off.
- `phil-dev bench:worker --ext` and `--build`: the worker served by the `frankenphp-ext` service, on the `bench-ext`
  image of `resources/bench-ext/Dockerfile` — FrankenPHP on Debian with the thread-safe PHP 8.5, intl, GD, production
  settings and the PIE extensions `PHIL_DEV_BENCH_EXT_PIE` names —, built on demand only.
- `package.json` with `@cspell/dict-fr-fr` as a development dependency at an exact version, which `lint-docs` reads
  from the `node_modules/` of the root or installs under `var/phil-dev/node/` with npm, and imports by its absolute
  path in the configurations of cspell.
- `lou_translate` and the tables of liblouis in the image of `phil-dev php`, for the integration tests of a braille
  translator.
- `phil-dev split` adds, in a commit on top of each split commit, `.github/dependabot.yml` (Composer, npm when the
  member has a `package.json` at its root or under `assets/`, GitHub Actions; no pull request for version updates) and
  the OpenSSF Scorecard workflow, its actions pinned by commit, from `resources/security/split/`. The commit is built
  with git's plumbing, reproducible from the same split, and merges the tip of the published branch when the split
  does not descend from it, so that each push is a fast-forward. A member without a `SECURITY.md` is not split.
- `phil-dev split --security-files`: the `SECURITY.md` of the skeleton and the OpenSSF Scorecard badge of the README
  and its French mirror, added to the members that miss them, and, with the root's `split.snyk`, the Snyk policy
  `.snyk` and workflow of `resources/security/monorepo/` at the root of a public monorepo; nothing is split or
  committed.
- `check-packages` warns about a package without a `SECURITY.md`, or whose README shows no security badge.
- The skeleton writes the OpenSSF Scorecard badge of the package's split repository after the title of its README and
  of its French mirror.

## 0.1.2-alpha - 2026-11-12

### Added

- `infection.timeout` in `phil-dev.php`: the seconds a mutant's tests may take for a package whose tests are slow on
  purpose. `phil-dev infection` writes the longest timeout of the packages analysed in
  `var/phil-dev/infection.json`, for the global run as for the members `PHIL_DEV_PACKAGES` names; Infection's 10
  seconds stay the default, and a value other than a positive integer stops the command.
- The skeleton and `phil-dev init` write `config.policy.advisories.block: true` in `composer.json`: Composer itself
  refuses to install a version with a security advisory, whatever the global configuration says.

### Changed

- Without `docs.translations` in the package's manifest or the root's, the failures of `check-translations` block
  once the package is public (`docs.public`) and are advisory until then. The skeleton writes no `translations`
  line: a new package passes before its French documentation is written, and blocks from its release.

### Removed

- `roave/security-advisories` from the `require-dev` of the skeleton, which Composer's native blocking replaces;
  `composer audit` stays in the gates.

## 0.1.1-alpha - 2026-11-11

### Changed

- The version matrix raises the Symfony floor at the next minor version's first patch release (x.y.1); a Symfony
  major version and PHP still become the floor at their x.y.3.

## 0.1.0-alpha - 2026-11-11

### Added

- `phil-dev.php`, `Manifest`, `Package` and `Workspace`: each package's paths and exceptions in a file of its own; the
  root, the members found by the root's globs, and the installed packages that ship a manifest; `PHIL_DEV_PACKAGES` to
  analyse some members only.
- `PackageStatus` and the `status` of `phil-dev.php`: `active` by default, or `planned` for a package being written;
  `--skip-planned` on every command and `PHIL_DEV_SKIP_PLANNED=1` leave the planned members out of every gate, the
  command telling how many it skipped; `PhpUnitConfig` writes the root's PHPUnit configuration without them, for
  `test` and `infection`.
- The shared configurations of PHP-CS-Fixer, Rector, Arkitect, PHPStan and PHPBench, read through `Tooling`, with the
  forbidden calls of PHPStan, their identifiers and their witnesses.
- PHPStan in bleeding edge, with `phpstan-deprecation-rules`, the type coverage at 100 %, the cognitive complexity at
  25 per method and 100 per class, and a selection of the rules of `symplify/phpstan-rules`, in
  `config/phpstan-symplify.php`.
- The rules of `kocal/phpstan-symfony-ux` on Live Components and Twig Components, in `config/phpstan-symfony-ux.php`,
  without effect on a project that has no Symfony UX, except `ClassNameMustNotEndWithComponentRule`.
- `PhpStan\Rules\ClassNameMustEndWithComponentRule`, identifier `phil.componentClassName`: a class carrying
  `#[AsTwigComponent]` or `#[AsLiveComponent]` has a name ending with `Component`, the attribute compared by its name so
  that a project without Symfony UX runs it; loaded by `config/phpstan-symfony-ux.php`, with its witness.
- `DeptracConfig`: the Deptrac layers of every package, generated from its `composer.json` and `phil-dev.php`, with a
  shared default of Symfony components, contracts, polyfills and PSR interfaces, the dependencies each package is
  allowed, libraries that never see a bundle, and `Forbidden` closed by a negative lookahead.
- `InfectionConfig`: the Infection configuration generated from every package, at 100 % of mutants killed, with the
  named exceptions of each manifest.
- `phil-dev init`: the thin configuration files, `captainhook.json`, `vendor-bin/` for each tool, the Composer scripts
  and their descriptions, ParaTest and the slow-test detector, without replacing what differs unless forced, and never
  `phil-dev.php`; a `phpunit.dist.xml` that requires the coverage metadata of every test.
- `phil-dev test`: the tests through ParaTest, or PHPUnit without it or with `phpunit.parallel: false`; `test`,
  `infection` and `services test|record` with `TMPDIR` under the root's `var/tmp/`, a cache per checkout for the test
  kernels.
- The gates `audit` (`composer audit`), `unused` (composer-unused), `normalize` (composer-normalize, with `--fix`),
  `twig` (Twig-CS-Fixer) and `js` (Biome, downloaded through `kocal/biome-js-bundle`), each turned off by `gates` in
  the root's manifest, and `twigstan`, with its generated configuration and the shared Twig loader.
- `PsalmConfig` and `phil-dev taint`: the taint analysis of Psalm on the production code, with the excludes and
  reviewed suppressions of each manifest.
- `phil-dev fuzz`: the fuzz targets of each manifest run by `nikic/php-fuzzer` for a time budget, with their corpus;
  `ProcessRunner` runs a command with a timeout.
- `phil-dev sbom`: the CycloneDX bill of materials of each package, a member resolved in a copy.
- `phil-dev bc`: the public API against the latest tag, with roave/backward-compatibility-check.
- `phil-dev bench`, with `--baseline` and `--compare` at the threshold of `bench.threshold`, and `bench:worker`,
  `WorkerBench` and the FrankenPHP service of `resources/compose.yaml` for a worker-mode benchmark.
- `phil-dev split`: the finished members split to their repositories or merged into another monorepo, with
  splitsh-lite or git subtree, a dry run without `--push`.
- `CaptainHookConfig`: the fast gates on the staged files before each commit, installed by `composer hooks`.
- `DocsLintConfig` and `phil-dev lint-docs`: cspell, in English or French, the French mirrors in French, markdownlint
  and Vale with the shared words and style of `resources/docs/`; `Executables`, the programs of the `PATH`.
- `ChurnConfig` and `phil-dev hotspots`: the hotspots of the code, with churn-php.
- `phil-dev new`: the skeleton of a library or a bundle, with the wording of its vendor, the versions of the matrix, a
  normalized `composer.json` and `roave/security-advisories`, and the French mirror of its README and `doc/`, stamped
  with its English source; `--planned` for a package being written, with what it will do in its README, its status and
  a smoke test; `--requires`, `--title` and `--description-fr`.
- `phil-dev check-docs`: the file set, the links, the tables, the bold — code spans of any run of backticks and
  escaped pipes left out —, the README index and sections, one "Added" section until the packages are public, the
  licence holder's wording and the forbidden terms; `README.fr.md`, when there is one, indexes `doc/fr/`.
- `phil-dev check-translations` and `TranslationChecker`: `README.md` and `doc/` against `README.fr.md` and `doc/fr/`,
  a document or a mirror alone, the headings of each level and the code blocks failing, a translation behind its
  English reported, by the SHA-256 of `SourceStamp` on its first line; `docs.translations` for a blocking or advisory
  package; `phil-dev stamp-translations`; the `composer docs:translations` script and its pre-commit hook.
- `doc/fr-glossary.md`: the words and the tone of the French documentation.
- `phil-dev check-packages`: each package installed and tested on its own, on another Symfony version with `--symfony`;
  the siblings at the branch their branch alias names, from a worktree as from the main checkout; `TMPDIR` inside each
  copy.
- `phil-dev infection`, on the whole code or on the files changed since a base ref; `phil-dev deptrac`,
  `phil-dev witnesses` and `phil-dev generate`.
- `phil-dev services` and `resources/compose.yaml`: MySQL 8, PostgreSQL 17 and Gotenberg 9 for the integration tests,
  their variables, and `ServiceUrl`.
- `HttpRecording` and `phil-dev services record`: the HTTP exchanges of integration tests recorded and replayed offline
  through the recorder of Symfony 8.2, with their secrets masked.
- `config/matrix.json`, `Matrix`, `phil-dev matrix` and `phil-dev php`: the floor and the next versions of PHP and
  Symfony, the PHP range of PHPStan, and the commands run in the Docker image of a PHP version, with GD for WebP and
  AVIF, the `sqlite3` command, and the neighbouring directories of the path repositories mounted read-only.
- `MemberAutoloader`: the classes of a monorepo's members loaded from their own `composer.json`, their prefixes added to
  Composer's `ClassLoader`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
