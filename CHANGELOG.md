# Changelog

All notable changes to `phil/dev-tools` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles. Until the packages are launched
publicly, split to their public repositories, this file keeps one "Added" section that describes the current state.

## NEXT - YYYY-MM-DD

### Added

- `phil-dev.php`, `Manifest`, `Package` and `Workspace`: each package's paths and exceptions in a file of its own; the
  root, the members found by the root's globs, and the installed packages that ship a manifest; `PHIL_DEV_PACKAGES` to
  analyse some members only.
- The shared configurations of PHP-CS-Fixer, Rector, Arkitect, PHPStan and PHPBench, read through `Tooling`, with the
  forbidden calls of PHPStan, their identifiers and their witnesses.
- PHPStan in bleeding edge, with `phpstan-deprecation-rules`, the type coverage at 100 %, the cognitive complexity at
  25 per method and 100 per class, and the rules of `symplify/phpstan-rules` La Page à Melkor chose, in
  `config/phpstan-symplify.php`.
- The rules of `kocal/phpstan-symfony-ux` on Live Components and Twig Components, in `config/phpstan-symfony-ux.php`,
  without effect on a project that has no Symfony UX.
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
- `DocsLintConfig` and `phil-dev lint-docs`: cspell, in English or French, markdownlint and Vale with the shared words
  and style of `resources/docs/`; `Executables`, the programs of the `PATH`.
- `ChurnConfig` and `phil-dev hotspots`: the hotspots of the code, with churn-php.
- `phil-dev new`: the skeleton of a library or a bundle, with the wording of its vendor, the versions of the matrix, a
  normalized `composer.json` and `roave/security-advisories`.
- `phil-dev check-docs`: the file set, the links, the tables, the bold, the README index and sections, one "Added"
  section until the packages are public, the licence holder's wording and the forbidden terms.
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
