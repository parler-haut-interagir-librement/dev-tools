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

PHPStan reads the workspace through a file generated under `var/phil-dev/`, because it keys its cache on the content of
its configuration files and would otherwise miss a change in a `phil-dev.php`.

## phil-dev.php

A PHP file that returns an array. Every section is optional; paths are relative to the file.

```php
<?php

declare(strict_types=1);

return [
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
    'docs' => ['public' => false],
];
```

| Section | Key | Meaning |
|---|---|---|
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
| `phpunit` | | Reserved. |

## A monorepo

The root's `phil-dev.php` lists its own paths and the globs of its members. Two more pieces avoid every central list:

- **Autoloading**: the root's `composer.json` loads the members' classes from their own `composer.json` with
  `"autoload-dev": {"files": ["vendor/phil/dev-tools/resources/autoload-members.php"]}`, instead of listing each
  namespace.
- **Narrowing**: `PHIL_DEV_PACKAGES=tilttek-quests,tilttek-quests-bundle composer gates` runs every gate on those
  members only; the generated rules still know every package, so the result is the same as in a full run.
