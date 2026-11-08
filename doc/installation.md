# Installation and usage

## In a package or a monorepo root

```sh
composer require --dev phil/dev-tools
vendor/bin/phil-dev init
composer update
composer deps:normalize:fix
composer hooks
```

`init` writes the thin configuration files at the root and adds the Composer scripts, the bin plugin and the
development dependencies to `composer.json`. A directory that holds `packages/` is set up as a monorepo root;
`--package` and `--monorepo` choose explicitly. Nothing that exists with other content is replaced unless `--force`
is given, and `phil-dev.php` is never replaced. [Commands](commands.md) describes every option.

`composer update` then installs the quality tools, each in its own `vendor-bin/<tool>/` through
`bamarni/composer-bin-plugin`, so that their dependencies never meet the project's. `composer deps:normalize:fix`
sorts the scripts `init` added, and `composer hooks` installs the git hooks of `captainhook.json`.

## Every day

| Script | What it runs |
|---|---|
| `composer gates` | Every gate in order: dependency analysis, advisories, unused packages, normalized `composer.json`, code style of PHP, Twig and scripts, PHPStan, Rector, Deptrac, Arkitect, PHPUnit, Infection. |
| `composer cs:fix`, `rector:fix`, `twig:fix`, `js:fix`, `deps:normalize:fix` | The fixes of PHP-CS-Fixer, Rector, Twig-CS-Fixer, Biome and composer-normalize. |
| `composer infection:changed` | Mutation testing of the files changed since the base ref, once per batch before its commit. |
| `composer packages` | Each package of a monorepo installed and tested on its own. |
| `composer docs` | The documentation checks of each package. |
| `composer test:next`, `packages:next`, `infection:next` | The same on the next PHP of the [version matrix](version-matrix.md). |
| `composer test:services` | The Integration suite against the [real servers](services.md). |

## Before a release, and once a week

| Script | What it runs |
|---|---|
| `composer taint`, `fuzz`, `sbom` | The taint analysis, the fuzz targets and the bill of materials: see [Security checks](security.md). |
| `composer bc` | The public API against the latest tag, in a package's own repository. |
| `composer twigstan` | The Twig templates analysed by TwigStan. |
| `composer bench:baseline`, `bench:compare`, `bench:worker` | The benchmarks: see [Benchmarks](benchmarks.md). |
| `composer docs:lint`, `hotspots`, `split` | The prose, the hotspots and the split: see [The monorepo workflow](workflow.md). |

## A new package

```sh
vendor/bin/phil-dev new phil/example
vendor/bin/phil-dev new tilttek/example --bundle
```

[The package skeleton](skeleton.md) describes what is written.

## Requirements

PHP 8.5 or later, and Docker for the real servers and the next PHP of the matrix.
