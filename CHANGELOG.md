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
- `DeptracConfig`: the Deptrac layers of every package, generated from its `composer.json` and `phil-dev.php`, with a
  shared default of Symfony components, contracts, polyfills and PSR interfaces, the dependencies each package is
  allowed, libraries that never see a bundle, and `Forbidden` closed by a negative lookahead.
- `InfectionConfig`: the Infection configuration generated from every package, at 100 % of mutants killed, with the
  named exceptions of each manifest.
- `phil-dev init`: the thin configuration files, `vendor-bin/` for each tool, the Composer scripts and their
  descriptions, without replacing what differs unless forced, and never `phil-dev.php`.
- `phil-dev new`: the skeleton of a library or a bundle, with the wording of its vendor and the versions of the matrix.
- `phil-dev check-docs`: the file set, the links, the tables, the bold, the README index and sections, one "Added"
  section until the packages are public, the licence holder's wording and the forbidden terms.
- `phil-dev check-packages`: each package installed and tested on its own, on another Symfony version with `--symfony`.
- `phil-dev infection`, on the whole code or on the files changed since a base ref; `phil-dev deptrac`,
  `phil-dev witnesses` and `phil-dev generate`.
- `phil-dev services` and `resources/compose.yaml`: MySQL 8, PostgreSQL 17 and Gotenberg 9 for the integration tests,
  their variables, and `ServiceUrl`.
- `HttpRecording` and `phil-dev services record`: the HTTP exchanges of integration tests recorded and replayed offline
  through the recorder of Symfony 8.2, with their secrets masked.
- `config/matrix.json`, `Matrix`, `phil-dev matrix` and `phil-dev php`: the floor and the next versions of PHP and
  Symfony, the PHP range of PHPStan, and the commands run in the Docker image of a PHP version.
- `MemberAutoloader`: the classes of a monorepo's members loaded from their own `composer.json`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
