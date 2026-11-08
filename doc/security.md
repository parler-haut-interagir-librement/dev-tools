# Security checks

Five checks look for what a vulnerability is made of: a dependency with an advisory, an input that reaches a page or a
query unescaped, a parser that breaks on an input nobody wrote, and what a package brings with it.

| Check | Script | Runs |
|---|---|---|
| The advisories of the installed packages | `composer deps:audit` | in the gates |
| No package with an advisory ever installed | `roave/security-advisories` | at every `composer update` |
| The taint analysis of Psalm | `composer taint` | on its own, weekly and before each release |
| Fuzzing the parsers | `composer fuzz` | on its own, weekly and after a change to a parser |
| The bill of materials | `composer sbom` | on its own, before each release |

## The advisories

`phil-dev audit` runs `composer audit` on the root: every installed package, development ones included, against the
advisories of Packagist. An abandoned package is reported without failing. The skeleton requires
`roave/security-advisories` in `require-dev`: it conflicts with every version that has an advisory, so that
Composer refuses to install one in the first place.

## The taint analysis

PHPStan stays the analyser of the gates. Psalm, in its own `vendor-bin/psalm/`, runs in taint analysis mode only: it
follows what comes from a request, a file or the environment to an HTML page, an SQL query, a shell, a file path or
`unserialize()`, through every call.

`phil-dev taint` writes `var/phil-dev/psalm.xml` from the workspace: the source directories of every package analysed,
without the tests, at the lowest error level, since only the taint issues matter here. A manifest adds paths left out,
and the known, reviewed exceptions by issue type:

```php
'psalm' => [
    'excludes' => ['src/Generated'],
    // The renderer escapes every value itself; the raw output is its job.
    'suppress' => ['TaintedHtml' => ['src/Renderer/RawRenderer.php']],
],
```

## Fuzzing

A parser of markup, CSS, SVG, dice notation or JSON documents meets inputs nobody wrote. `nikic/php-fuzzer`, in its
own `vendor-bin/fuzzer/`, mutates a corpus of inputs towards the code they do not reach yet, and stops on the first
input that throws what the target does not expect.

A package declares its targets in its `phil-dev.php`, each a name and a PHP file:

```php
'fuzz' => [
    'targets' => ['markup' => 'tests/Fuzz/markup.php'],
    'budget' => 120,
    'corpus' => 'tests/Fuzz/corpus',
],
```

The target file loads the classes through `PHIL_DEV_AUTOLOAD`, which `phil-dev fuzz` sets, and gives the fuzzer its
target:

```php
<?php

declare(strict_types=1);

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        new Phil\Markup\Parser()->parse($input);
    } catch (Phil\Markup\Exception\ParseError) {
        // The error the parser promises for an input it cannot read.
    }
});
$config->setMaxLen(4096);
```

`phil-dev fuzz [targets…] [--budget=SECONDS] [--max-runs=N]` fuzzes each target for its budget: `--budget`, else the
manifest's `fuzz.budget`, else 60 seconds. The corpus of a target is `<corpus>/<name>`: its seeds, committed, and the
inputs the fuzzer adds when they reach new code, committed when they are worth keeping. A crash fails the run and
leaves its input in `var/phil-dev/fuzz/<name>/`; it becomes a unit test once the parser handles it.

## The bill of materials

`phil-dev sbom [packages…]` writes `var/phil-dev/sbom/<vendor>-<name>.cdx.json` for each package: the CycloneDX 1.6
bill of materials of what installing it brings, without its development dependencies, made by
`cyclonedx/cyclonedx-php-composer` in its own `vendor-bin/sbom/`, reproducible from one run to the next.

A package with a `composer.lock` is read from it. A member of a monorepo has none: its `composer.json` is copied under
`var/phil-dev/sbom/work/`, with a path repository on each glob of the root's packages, and its dependencies are
resolved without being installed, which needs the network. The file is attached to each release.
