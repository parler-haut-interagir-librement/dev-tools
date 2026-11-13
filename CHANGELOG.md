# Changelog

All notable changes to `phil/dev-tools` will be documented in this file.

Updates follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles, and versions follow
[Semantic Versioning](https://semver.org/).

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
  escaped pipes left out —, the README index and sections, one "Added" section until the packages are public, the licence holder's wording and the forbidden terms; `README.fr.md`, when
  there is one, indexes `doc/fr/`.
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
