# Quality gates

`composer gates` runs them in this order, the order of a continuous integration job: the dependency analysis, the
advisories, the unused packages, the normalized `composer.json`, the code style of PHP, of the Twig templates and of
the scripts, PHPStan, Rector, Deptrac, Arkitect, the tests and Infection. A gate is in it when it is reliable and fast
enough for every batch; the slower checks are scripts of their own.

| Check | Script | Where |
|---|---|---|
| Dependency analysis, advisories, unused packages, normalized `composer.json` | `deps`, `deps:audit`, `deps:unused`, `deps:normalize` | the gates |
| PHP-CS-Fixer, Twig-CS-Fixer, Biome | `cs`, `twig`, `js` | the gates |
| PHPStan and its witnesses, Rector, Deptrac, Arkitect | `phpstan`, `rector`, `deptrac`, `arkitect` | the gates |
| PHPUnit through ParaTest, Infection | `test`, `infection` | the gates |
| TwigStan | `twigstan` | its own script: young, and it compiles every template |
| The taint analysis, fuzzing, the bill of materials | `taint`, `fuzz`, `sbom` | their own scripts: see [Security checks](security.md) |
| The backward-compatibility check | `bc` | its own script: it installs two revisions |
| The benchmarks against their baseline | `bench:compare` | its own script: see [Benchmarks](benchmarks.md) |
| The prose of the documentation, the hotspots | `docs:lint`, `hotspots` | their own scripts: see [The monorepo workflow](workflow.md) |

The root's `phil-dev.php` turns off one of the gates `audit`, `unused`, `normalize`, `twig` and `js` with
`'gates' => ['<name>' => false]`: the gate then says it is off and passes. The fast gates also run on the staged files
before each commit, through the hooks of [The monorepo workflow](workflow.md#the-hooks).

## The dependency analysis

`composer-dependency-analyser` with the configuration of the project: a class used from a package `composer.json` does
not require, production code using a development dependency, a production dependency used only in tests.
`phil-dev check-packages` runs it again on each package installed on its own.

## The advisories, the unused packages and the normalized composer.json

`phil-dev audit` runs `composer audit`: see [Security checks](security.md#the-advisories). `phil-dev unused` runs
composer-unused on the root's `composer.json`: a package of `require` whose symbols no autoloaded file uses. It
complements the dependency analysis, which also finds the symbols used without being required; a package used only
through configuration is named in a manifest's `unused.ignore`. The members of a monorepo are checked on their own by
`check-packages`.

`phil-dev normalize` runs composer-normalize on the `composer.json` of the root and of each member analysed, with four
spaces, and fails on a difference, which it shows; `--fix` writes the normalized files. The lock files are left alone.

## PHP-CS-Fixer

`@Symfony`, `@Symfony:risky` and the PHP migration sets read from the `php` constraint of `composer.json`, with
`declare(strict_types=1)` everywhere and strict comparisons. Dates are never compared with `==` nor `===`: the second
compares the identity of two objects, not their instant.

## Twig templates

`phil-dev twig` runs Twig-CS-Fixer on the templates of every package analysed: the directories of each manifest's
`twig.paths`, else `templates`, `src/Resources/views` and `Resources/views` when they exist. A workspace without
templates passes.

`phil-dev twigstan` compiles the templates to PHP and analyses them at the maximum level, with the types of the
context the PHP code renders them with. TwigStan runs its own PHPStan, without the extensions of the gates. The shared
Twig loader only knows the template directories: a package whose templates need its own extensions names, under
`twig.loader`, a PHP file that returns its `Twig\Environment`.

## Scripts

`phil-dev js` runs Biome on the scripts of every package analysed — the Stimulus controllers — from the directories of
each manifest's `js.paths`, else `assets`: the recommended rules, and a format close to the PHP code style, four
spaces, single quotes and 120 columns. Biome is a single binary, without Node.js: the first run downloads it to
`var/phil-dev/bin/` through `kocal/biome-js-bundle`, at the version of the root's `js.biome` or the one this package
pins. A manifest's `js.rules` changes a rule by group.

## PHPStan

The maximum level with the strict rules, the uninitialised properties, the benevolent unions and the PHPDoc types
taken as certain, for every PHP of the matrix: `phpVersion` goes from the floor to the last release of the next
version. Every exception that no longer matches fails, except the shared ones of the tests. Bleeding edge brings the
checks of the next major version of PHPStan now.

Three extensions add to it:

- `phpstan-deprecation-rules`: a call to a deprecated class, method or function fails, so that the next version of a
  dependency removes nothing still used;
- `tomasvotruba/type-coverage`, at 100 %: every parameter, return, property and constant is typed natively, a PHPDoc
  type refining a native one, never replacing it;
- `tomasvotruba/cognitive-complexity`: 25 for a method, 100 for a class. A method above the bar is split, or carries
  its exception in its package's `phil-dev.php`, with the reason.

`config/phpstan-symplify.php` adds a selection of the rules of `symplify/phpstan-rules`, one by one: a class is
final or abstract, no global constant, exceptions in an `Exception` namespace, attributes in an `Attribute`
namespace, interfaces in a `Contract` namespace — a marker interface of exceptions stays with them —, unique enum
values, no narrowed visibility of a parent method, one class per file, no `static::CONSTANT`, no method call on an
array, no misnamed doc tag. `phpstan/extension-installer` ignores the package, which would load every set.

`config/phpstan-symfony-ux.php` adds the rules of `kocal/phpstan-symfony-ux`, one by one, so that a new rule of the
package is a decision. They look at a class only when it carries `#[AsTwigComponent]` or `#[AsLiveComponent]`, and a
project without Symfony UX is not affected:

| On | The rules check |
|---|---|
| Live Components | the `method` and `fetchCredentials` of the attribute; `#[LiveAction]` and `#[LiveListener]` methods are public; the methods of `hydrateWith`, `dehydrateWith` and `modifier` exist, with the right signature; a `LiveResponse` comes from a live action or listener |
| Every component | the class is final, its name does not end with `Component`, `exposePublicProps` is `false`, no `class` or `attributes` property, no readonly property outside the constructor, no protected method, public properties in camelCase; `#[PreMount]`, `#[PostMount]` and `FromMethod` have the right signature |

With `exposePublicProps: false`, a template reads `this.limit`, not `limit`. A readonly component class that only
holds its injected services stays allowed: Rector asks for that keyword, so
`symfonyUX.twigComponent.forbiddenReadonlyClass` is ignored in `config/phpstan.neon`. `ForbiddenAttributesPropertyRule`
needs the class of the attribute: it is loaded only when the project it runs from has Symfony UX, since a package with
an optional component would otherwise stop PHPStan. A project whose `vendor-bin/phpstan` does not have the package gets
none of these rules.

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
stops it, in backticks after `Expected identifier:`. `phil-dev witnesses` fails when PHPStan no longer reports that identifier
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

`phil-dev test` runs the suites with ParaTest, one process per core. Every test names what it covers, with
`#[CoversClass]` or `#[CoversNothing]` (`requireCoverageMetadata`): coverage counts only what a test sets out to test,
and Infection replays only the tests that cover a mutant. `ergebnis/phpunit-slow-test-detector` lists the tests above
half a second; a test slow on purpose says so with `#[MaximumDuration]`.

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
