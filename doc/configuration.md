# Configuration

## Layering

Every configuration has three layers:

1. **The shared configuration**, in `config/` of this package: the rules every PHIL and Tilttek package follows.
2. **The thin file** at the root of a project, written by `init`: it calls the shared configuration with its own
   directory, and holds what only the project needs — a Symfony container for PHPStan, a rule of its own.
3. **`phil-dev.php`**, in the root and in each package: the package's paths and its exceptions, nothing else.

The shared configurations read every `phil-dev.php` themselves, so adding a package to a monorepo adds a directory and
touches no file of the root: several batches can work at once in their own worktrees without conflicts.

| Tool | Thin file | How it is layered |
|---|---|---|
| PHP-CS-Fixer | `.php-cs-fixer.dist.php` | Calls `config/php-cs-fixer.php`, which returns the `Config`. |
| Rector | `rector.php` | Calls `config/rector.php`, which returns the `RectorConfigBuilder`. |
| Arkitect | `arkitect.php` | Calls `config/arkitect.php`, which returns the closure Arkitect runs. |
| PHPStan | `phpstan.neon` | Includes `config/phpstan.neon`, which includes the forbidden calls and the paths of the workspace. |
| Deptrac | none | `phil-dev deptrac` generates the configuration; the root may add its own files. |
| Infection | none | `phil-dev infection` generates the configuration: Infection has no includes. |
| PHPUnit | `phpunit.dist.xml` | Written whole by `init`, with globs for the packages of a monorepo. |
| PHPBench | `phpbench.json` | Includes `config/phpbench.json`. |
| Psalm | none | `phil-dev taint` generates the configuration of the taint analysis. |
| TwigStan | none | `phil-dev twigstan` generates its configuration and its PHPStan configuration. |
| Twig-CS-Fixer | none | The tool's standard, on the template directories. |
| Biome | none | `phil-dev js` generates the configuration. |
| churn-php | none | `phil-dev hotspots` generates the configuration. |
| cspell, markdownlint, Vale | none | `phil-dev lint-docs` generates their configurations. |
| CaptainHook | `captainhook.json` | Written whole by `init`. |

PHPStan reads the workspace through a file generated under `var/phil-dev/`, because it keys its cache on the content of
its configuration files and would otherwise miss a change in a `phil-dev.php`.

## phil-dev.php

A PHP file that returns an array. Every section is optional; paths are relative to the file.

```php
<?php

declare(strict_types=1);

return [
    'status' => 'active',
    'paths' => ['src', 'tests'],
    'phpstan' => [
        'includes' => ['extension.neon'],
        'excludePaths' => ['src/Generated/*'],
        'ignoreErrors' => [
            // why, in one sentence
            ['identifier' => 'argument.type', 'path' => 'src/Legacy.php'],
        ],
    ],
    'rector' => ['skip' => [SomeRector::class => ['src/Legacy.php']]],
    'cs' => ['exclude' => ['Generated']],
    'infection' => [
        'excludes' => ['Infrastructure'],
        'mutators' => ['IncrementInteger' => ['ignoreSourceCodeByRegex' => ['private const int MAX_.*']]],
    ],
    'deptrac' => ['allow' => ['^League\\\\Uri\\\\.*']],
    'arkitect' => ['namespaces' => ['Legacy'], 'rules' => static fn (): array => []],
    'docs' => ['public' => false, 'language' => 'en', 'words' => ['Example'], 'translations' => 'blocking'],
    'gates' => ['js' => false],
    'phpunit' => ['parallel' => true],
    'psalm' => ['excludes' => ['src/Generated'], 'suppress' => ['TaintedHtml' => ['src/RawRenderer.php']]],
    'fuzz' => ['targets' => ['markup' => 'tests/Fuzz/markup.php'], 'budget' => 60],
    'bench' => ['threshold' => 10, 'worker' => ['script' => 'tests/Bench/worker.php']],
    'bc' => ['tags' => 'v*'],
    'split' => ['repository' => 'git@github.com:parler-haut-interagir-librement/example.git'],
    'twig' => ['paths' => ['templates'], 'loader' => 'tests/twig-loader.php'],
    'js' => ['paths' => ['assets/controllers'], 'rules' => ['style' => ['noNonNullAssertion' => 'off']]],
    'unused' => ['ignore' => ['symfony/polyfill-php86']],
    'hotspots' => ['files' => 10, 'since' => 'One year ago'],
];
```

| Section | Key | Meaning |
|---|---|---|
| `status` | | `active`, the default, or `planned`: a package being written; see [Planned packages](#planned-packages). |
| `paths` | | The directories and files the tools read; `src` and `tests` by default. |
| `packages` | | Root only: the globs of the members of a monorepo; `packages/*` by default. |
| `phpstan` | `includes` | Extensions of the package that PHPStan loads, such as the PHPStan extension a library ships. |
| | `excludePaths` | Paths PHPStan leaves out. |
| | `ignoreErrors` | The package's exceptions, each with a reason; unmatched ones fail. |
| `rector` | `skip` | Rules, paths, or rules with the paths they skip. |
| `cs` | `exclude` | Paths PHP-CS-Fixer leaves out, relative to each directory read. |
| | `rules` | Root only: rules added to or changed in the shared code style. |
| `infection` | `directories` | The source directories; those of `autoload` by default. |
| | `excludes` | Paths left out of mutation, relative to each source directory. |
| | `mutators` | The named exceptions of the package, merged with the others. |
| | `testFrameworkOptions` | Root only: the PHPUnit options; `--testsuite=Unit` by default. |
| `deptrac` | `allow` | The dependencies the package is allowed besides the default; see [Dependencies](dependencies.md). |
| | `layers`, `ruleset` | Layers of the package's own, and rules replacing the defaults of a layer. |
| | `default`, `configs` | Root only: the default replaced; extra Deptrac configurations. |
| `arkitect` | `namespaces` | Namespaces admitted besides those of `autoload`. |
| | `rules` | A callable returning extra Arkitect rules. |
| `docs` | `public` | Set once the package is split to its public repository: the CHANGELOG may then use every section. |
| | `forbidden` | Root only: regular expressions of the terms no package names. |
| | `language` | `en`, the default, or `fr`: the language cspell reads; Vale reads English only. The root's is its members' default. |
| | `words` | Words cspell accepts besides the shared list; the root's apply to every member. |
| | `markdownlint` | Root only: markdownlint rules over the shared ones. |
| | `translations` | `blocking`, the default, or `advisory`: whether the failures of `check-translations` stop the gate. A package's value wins over the root's; see [Quality gates](quality-gates.md#while-the-existing-packages-catch-up). |
| `gates` | `audit`, `unused`, `normalize`, `twig`, `js` | Root only: `false` turns that gate off. |
| `phpunit` | `parallel` | Root only: `false` runs the tests with PHPUnit instead of ParaTest. |
| `psalm` | `excludes` | Paths the taint analysis leaves out. |
| | `suppress` | An issue type, such as `TaintedHtml`, with the paths where it is a reviewed exception. |
| `fuzz` | `targets` | A name and the PHP file of each fuzz target. |
| | `budget`, `corpus` | The seconds each target is fuzzed for, 60 by default; the corpus directory, `tests/Fuzz/corpus` by default. |
| `bench` | `threshold` | Root only: the slowdown `bench:compare` allows, in percent; 10 by default. |
| | `worker` | The `script`, `requests` and `warmup` of the worker-mode benchmark. |
| `bc` | `tags` | Root only: the glob of the tags `bc` compares with; every tag by default. |
| `split` | `repository`, `branch`, `prefix` | Where a finished member is split to, its branch, and its directory in a target monorepo. |
| `twig` | `paths`, `loader` | The template directories; a PHP file returning the `Twig\Environment` TwigStan compiles with. |
| `js` | `paths`, `rules` | The script directories; Biome rules by group. |
| | `biome` | Root only: the Biome version downloaded. |
| `unused` | `ignore` | Packages composer-unused leaves out, used only through configuration. |
| `hotspots` | `files`, `since` | Root only: how many files churn-php shows, and from when it reads the history. |

## A monorepo

The root's `phil-dev.php` lists its own paths and the globs of its members. Two more pieces avoid every central list:

- **Autoloading**: the root's `composer.json` loads the members' classes from their own `composer.json` with
  `"autoload-dev": {"files": ["vendor/phil/dev-tools/resources/autoload-members.php"]}`, instead of listing each
  namespace. The members' PSR-4 prefixes are added to Composer's `ClassLoader` of the root, so that the tools that ask
  it where a class lives (`findFile()`, `getPrefixesPsr4()`) find them too.
- **Narrowing**: `PHIL_DEV_PACKAGES=tilttek-quests,tilttek-quests-bundle composer gates` runs every gate on those
  members only; the generated rules still know every package, so the result is the same as in a full run.

## Planned packages

A package being written keeps `'status' => 'planned'` in its `phil-dev.php`: its skeleton, a README that describes
what it will do, and a smoke test, written by `phil-dev new --planned` (see [The package skeleton](skeleton.md#a-planned-package)).
It is incubated with the other members: every gate checks it, as it checks an active package.

While the code of the active packages is what a run is about, the planned ones are left out:

```sh
PHIL_DEV_SKIP_PLANNED=1 composer gates
vendor/bin/phil-dev check-docs --skip-planned
```

- `PHIL_DEV_SKIP_PLANNED` accepts `1`, `true`, `yes` and `on`; any other value, or none, keeps the planned packages.
- `--skip-planned`, which every `phil-dev` command takes, sets the variable for the tools the command starts.
- Each `phil-dev` command that leaves them out first writes, on the error output, how many it skipped and which:
  `Skipped 2 planned packages: phil/a, tilttek/b.`
- The configurations of PHP-CS-Fixer, Rector, PHPStan and Arkitect read the variable through `Tooling`, so the
  Composer scripts that call those tools directly leave the planned packages out too, without a word.
- `test` and `infection` run with `var/phil-dev/phpunit/phpunit.xml`: the root's PHPUnit configuration, each relative
  path made absolute, each skipped package excluded from every suite and from the source.
- A command still works on a planned package named as its argument, and the root is never skipped.
- The variable combines with `PHIL_DEV_PACKAGES`: the members it names, less the planned ones.
