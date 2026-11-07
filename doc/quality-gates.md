# Quality gates

`composer portes` runs them in this order, the order of a continuous integration job.

## The dependency analysis

`composer-dependency-analyser` with the configuration of the project: a class used from a package `composer.json` does
not require, production code using a development dependency, a production dependency used only in tests.
`phil-dev check-packages` runs it again on each package installed on its own.

## PHP-CS-Fixer

`@Symfony`, `@Symfony:risky` and the PHP migration sets read from the `php` constraint of `composer.json`, with
`declare(strict_types=1)` everywhere and strict comparisons. Dates are never compared with `==` nor `===`: the second
compares the identity of two objects, not their instant.

## PHPStan

The maximum level with the strict rules, the uninitialised properties, the benevolent unions and the PHPDoc types
taken as certain, for every PHP of the matrix: `phpVersion` goes from the floor to the last release of the next
version. Every exception that no longer matches fails, except the three shared ones of the tests.

The forbidden calls are the sets of `spaze/phpstan-disallowed-calls` — dangerous, execution, insecure, loose and
non-timing-safe calls — and these, each with its identifier:

| Identifier | Forbidden | Allowed in the test suites |
|---|---|---|
| `phil.unserialize` | `unserialize()` | no |
| `phil.serialize` | `serialize()` | yes |
| `phil.dump` | `var_dump()`, `print_r()`, `var_export()`, `dd()`, `dump()` | yes |
| `phil.weakRandom` | `uniqid()`, `rand()`, `mt_rand()`, `shuffle()`, `array_rand()` | no |
| `phil.componentDispatcher` | the component's `EventDispatcherInterface` | no |

A **witness** is a file under `tests/Witnesses/PhpStan` that must fail: its docblock announces the identifier that
stops it, `Expected identifier: `phil.dump``. `phil-dev witnesses` fails when PHPStan no longer reports that identifier
in that file, so that a rule that went silent fails the gate. The witnesses sit beside the test suites, which are
allowed by name (`tests/Unit`, `tests/Integration`, `tests/Functional`, `tests/Support`, `tests/Fixtures`), so that they
stay under every rule.

## Rector

The PHP sets read from `composer.json`, the Symfony, Doctrine and PHPUnit sets of the installed versions, and the
prepared sets, without a version number written anywhere. It skips, in `Domain` layers, the rules that would bring
Doctrine collections or DBAL constants in or make an entity `readonly`, and three rules whose choice the packages make
the other way.

## Deptrac and Arkitect

Deptrac checks where the dependencies point — see [Dependencies](dependencies.md). Arkitect checks what a class is: every
class in a namespace of the packages; `Domain\Model` final and not readonly; `Domain\ValueObject` final and readonly;
`Domain\Repository` interfaces named `*Repository`; nothing of Doctrine in a domain but the mapping attributes, nor the
validator, the container, HTTP, Messenger, the serializer, forms or security; no ORM nor DBAL in an application layer;
no domain event that is a Messenger envelope. Each rule applies to `<Namespace>\<Layer>` and `<Namespace>\*\<Layer>`.

## PHPUnit

`Unit` runs without a kernel or a server; `Integration` boots a kernel or talks to a real server, and is skipped
without one — or replays its recordings, see [HTTP recordings](http-recordings.md). Deprecations, notices and warnings
fail the run, on every PHP of the matrix.

## Infection

The configuration is generated under `var/phil-dev/infection.json`: the source directories of every package analysed,
their excludes and the named exceptions of each manifest. The bar is 100 % of mutants killed and of covered mutants
killed. A surviving mutant is a missing test, or code nothing can reach; a mutant that no test can tell apart is named
in the package's manifest with its reason, never excused by lowering the bar.

| When | On | Script |
|---|---|---|
| Once per batch, before its commit | the files changed since the base ref, on the floor PHP | `composer infection:changed` |
| Weekly and before each release | the whole code, on the floor PHP | `composer infection` |
| Weekly and before each release | the whole code, on the next PHP | `composer infection:next` |
