# Castor tasks

An application runs its tests and checks with [Castor](https://castor.jolicode.com) rather than Composer scripts.
`resources/castor/tasks.php` holds the tasks every application shares, written once: the application's `castor.php`
imports the file and keeps only its own tasks, such as starting its servers or resetting its database.

## Importing the tasks

`phil/dev-tools` is a development requirement of the application, so the file is already under `vendor/`:

```php
<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\exit_code;
use function Castor\import;
use function Phil\DevTools\Castor\success;
use function Phil\DevTools\Castor\title;

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

const PHIL_CASTOR = [
    'suites' => ['Unit', 'Integration', 'Functional'],
];

import(__DIR__.'/vendor/phil/dev-tools/resources/castor/tasks.php');

#[AsTask(namespace: 'symfony', description: 'Serve the application with the Symfony binary', aliases: ['start'])]
function start(): int
{
    title('symfony:start');

    return success(exit_code('symfony serve --daemon'));
}
```

The file is imported by its path rather than with the `composer://` import of Castor: that import installs a second
copy of the package and its dependencies under `.castor/`, with a lock of its own, while the application already
holds the version its `composer.lock` names. Castor is tested from version 1.9.

The tasks run from the directory of the project, whichever directory `castor` is called from: the file defines
`CASTOR_USE_CHDIR` to `true` when the application has not defined it before the import.

## The tasks

| Task | Alias | What it runs |
|---|---|---|
| `test:all` | `test` | The PHPUnit suites of `suites`, then the functions of `test_also`; `--filter` and `--options` go to PHPUnit, `--stop-on-failure` by default. |
| `test:suite <name>` | | One suite of `phpunit.dist.xml`. |
| `test:unit`, `test:integration`, `test:functional`, `test:api`, `test:e2e` | `test-unit`… | One suite each, with `--filter` and `--options`. |
| `test:next` | | The tests on the next PHP of the matrix, in its Docker image (`phil-dev php next`). |
| `test:coverage` | `coverage` | The suites with code coverage: the HTML report under `var/coverage/`, then the percentage of covered lines against `coverage_threshold`. |
| `test:infection` | `infection` | Mutation testing (`phil-dev infection`). |
| `security:taint` | `taint` | The taint analysis of Psalm (`phil-dev taint`). |
| `lint:php` | `lint-php` | The code style, with PHP-CS-Fixer. |
| `lint:twig` | `lint-twig` | `lint:twig` of the console and the code style of the templates; skipped without a `templates` directory. |
| `lint:js` | `lint-js` | The Stimulus controllers and the styles, with Biome. |
| `lint:yaml` | `lint-yaml` | `lint:yaml` of the console on the paths of `yaml` the project has. |
| `lint:container` | `lint-container` | `lint:container` of the console. |
| `lint:composer` | `lint-composer` | The dependency analysis, the advisories, the unused packages and the normalized `composer.json`. |
| `lint:analyse` | `analyse`, `stan` | PHPStan at the maximum level, then its witnesses; the dev container is warmed up first when its dump is missing. |
| `lint:rector` | `lint-rector` | Rector, in dry run. |
| `lint:architecture` | `lint-architecture` | Arkitect, then Deptrac. |
| `lint:all` | `lint` | Every lint above, then the functions of `lint_also`. |
| `fix:php`, `fix:rector`, `fix:twig`, `fix:js`, `fix:composer` | `fix-php`… | Each fixer. |
| `fix:all` | `fix` | Every fixer, Rector first, then the functions of `fix_also`. |
| `ci:all` | `ci` | The coverage, every lint, then the functions of `ci_also`. |
| `helpers:hooks` | `hooks` | Installs the git hooks of `captainhook.json`. |
| `helpers:versions` | `versions` | The versions of Castor, PHP, Composer, Symfony and the tools. |

A task that runs several steps runs them all and fails when one of them fails: its exit code is 1, never a sum.

## Settings

The constant `PHIL_CASTOR`, defined before the import, changes what the tasks read. Every key is optional; an unknown
key, or a value of the wrong type, stops the task with a message.

| Key | Default | What it sets |
|---|---|---|
| `suites` | `['Unit', 'Integration', 'Api', 'Functional', 'E2E']` | The suites of `test:all` and `test:coverage`, in order; never empty. |
| `coverage_threshold` | `100` | The percentage of covered lines `test:coverage` requires, a whole number from 0 to 100. |
| `yaml` | `['.github', 'config', 'compose.yaml', 'translations']` | The YAML files and directories of `lint:yaml`; those missing are left out. |
| `templates` | `'templates'` | The directory of the Twig templates. |
| `container` | `'var/cache/dev/App_KernelDevDebugContainer.xml'` | The dump of the dev container PHPStan reads. |
| `test_also`, `lint_also`, `fix_also`, `ci_also` | `[]` | The names of the application's own functions the aggregate task calls after its common steps. |

A function of `*_also` returns its exit code; one that returns nothing counts as a success. A name that is not a
function fails the task.

```php
const PHIL_CASTOR = [
    'suites' => ['Unit', 'Api', 'Functional'],
    'test_also' => ['test_schema'],
    'ci_also' => ['test_schema'],
];
```

## Helpers for the application's own tasks

The file declares its functions in the namespace `Phil\DevTools\Castor`, so they never clash with the application's:

| Function | Role |
|---|---|
| `title(string $name)` | Prints the description of the task as a title when it is the task the person called, not a step of another. |
| `success(int $exitCode): int` | Says how the task ended and passes the exit code on. |
| `aborted(string $message = 'Aborted')` | Warns that the person stopped the task. |
| `aggregate(int ...$exitCodes): int` | One exit code for several steps: 0 only when every step succeeded. |
| `settings(): Settings` | The settings of `PHIL_CASTOR` over the defaults. |

The task names above are taken: an application that wants a step of its own adds a task under another name, and names
it in the matching `*_also` key.

Castor runs without the application's autoloader: the file requires the few classes it reads, `Castor\Settings`,
`Castor\Commands` and their exception, by their path, and they depend on nothing but PHP.
