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
| The file set and the links of the documentation | `docs` | its own script and the hooks: see [The package skeleton](skeleton.md#the-documentation-checks) |
| The French mirror of the documentation | `docs:translations` | its own script and the hooks: see [below](#the-french-mirror-of-the-documentation) |

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

`config/phpstan-symfony-ux.php` adds the rule of this package on the names of components, then the rules of
`kocal/phpstan-symfony-ux`, one by one, so that a new rule of that package is a decision. They look at a class only
when it carries `#[AsTwigComponent]` or `#[AsLiveComponent]`, and a project without Symfony UX is not affected.

### A component's name ends with `Component`

`ClassNameMustEndWithComponentRule`, identifier `phil.componentClassName`, requires that a class carrying
`#[AsTwigComponent]` or `#[AsLiveComponent]` has a name that ends with `Component`, with that exact case.

A suffix tells the role of a class at a glance, in a file list, a stack trace or an import, the way `Interface`, `Trait`
and `Abstract…` do for theirs: `AlertComponent` is read as a component before its file is opened. It also keeps the
name clear of the words PHP reserves: a list component cannot be called `List`, while `ListComponent` is a valid name.
The name the templates use stays what the attribute says, so `<twig:Alert />` keeps working.

Refused, with the message `Class Alert carries #[AsTwigComponent]: its name must end with "Component", such as
AlertComponent.`, reported on the line of the class name:

```php
#[AsTwigComponent('Alert')]
final class Alert
{
}
```

Accepted:

```php
#[AsTwigComponent('Alert')]
final class AlertComponent
{
}
```

The rule compares the resolved name of the attribute, an alias or a different case included, and never loads its class:
a project without Symfony UX runs it as any other, and a package with an optional component does not stop PHPStan.
An attribute of the same short name from another namespace, and an anonymous class, are left alone. The rule replaces
`ClassNameMustNotEndWithComponentRule` of `kocal/phpstan-symfony-ux`, which asks the opposite and is left out of
`config/phpstan-symfony-ux.php`. The rule is loaded even when `vendor-bin/phpstan` does not have that package, and its
witness, `tests/Witnesses/PhpStan/Alert.php`, proves that the shared configuration still loads it.

### The rules of `kocal/phpstan-symfony-ux`

| On | The rules check |
|---|---|
| Live Components | the `method` and `fetchCredentials` of the attribute; `#[LiveAction]` and `#[LiveListener]` methods are public; the methods of `hydrateWith`, `dehydrateWith` and `modifier` exist, with the right signature; a `LiveResponse` comes from a live action or listener |
| Every component | the class is final, `exposePublicProps` is `false`, no `class` or `attributes` property, no readonly property outside the constructor, no protected method, public properties in camelCase; `#[PreMount]`, `#[PostMount]` and `FromMethod` have the right signature |

With `exposePublicProps: false`, a template reads `this.limit`, not `limit`. A readonly component class that only
holds its injected services stays allowed: Rector asks for that keyword, so
`symfonyUX.twigComponent.forbiddenReadonlyClass` is ignored in `config/phpstan.neon`. `ForbiddenAttributesPropertyRule`
needs the class of the attribute: it is loaded only when the project it runs from has Symfony UX, since a package with
an optional component would otherwise stop PHPStan. A project whose `vendor-bin/phpstan` does not have the package gets
none of these rules, and keeps the rule on the suffix.

### The forbidden calls and their witnesses

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

A mutant whose tests run longer than the timeout counts as timed out. Infection's timeout, 10 seconds, stays the
default; a package whose tests are slow on purpose, such as a search index built for each test, gives its own in its
`phil-dev.php`:

```php
return [
    'infection' => ['timeout' => 60],
];
```

Infection reads one configuration, so `var/phil-dev/infection.json` takes the longest timeout of the packages
analysed: the whole monorepo in the global run, the members `PHIL_DEV_PACKAGES` names when it narrows them. The value
is a positive integer; anything else stops the command.

| When | On | Script |
|---|---|---|
| Once per batch, before its commit | the files changed since the base ref, on the floor PHP | `composer infection:changed` |
| Weekly and before each release | the whole code, on the floor PHP | `composer infection` |
| Weekly and before each release | the whole code, on the next PHP | `composer infection:next` |

## The French mirror of the documentation

Every package is documented in English and in French. The English does not move: `README.md` and `doc/<name>.md`. The
French sits beside it, in `README.fr.md` and `doc/fr/<name>.md`, with the same names and the same tree. `phil-dev
check-translations`, or `composer docs:translations`, compares the two for each package; `PHIL_DEV_PACKAGES` or the
names given to it narrow them.

| Code | Means | Effect |
|---|---|---|
| `translation-missing` | An English document without its mirror. | Fails. |
| `translation-orphan` | A mirror without its English document. | Fails. |
| `translation-headings` | Not as many headings of a level as the English, fenced code left out. | Fails. |
| `translation-code` | A fenced code block, info string included, that differs from the English, or not as many blocks. | Fails. |
| `translation-late` | The English has changed since the translation, or the mirror has no stamp. | Reported. |

A late translation whose structure has drifted fails too, on its headings or its code. `doc/fr-glossary.md`, written
in French, has no English document.

### How a late translation is found

The first line of a mirror is an HTML comment, which no reader sees, with the SHA-256 of the English file it was
translated from:

```markdown
<!-- phil-dev source-sha256=c5c7ca5b657240367e91e4363fc79313246bb93458625263129fda313c8c3214 -->
```

The gate hashes the English again and compares. The dates of files say nothing, since a checkout rewrites them. The
history of git would be no better: a shallow clone and a split repository lack it, and a commit that touches both
files does not say that the French caught up. The content needs neither, and the same check gives the same answer on
every machine. Line endings are normalized before hashing, so that a checkout that converts them changes nothing.

Once the French has caught up, record it:

```sh
phil-dev stamp-translations phil/example
phil-dev stamp-translations phil/example --file=doc/installation.md
```

The command names its packages, so that no run marks a whole monorepo as translated, and `--file`, with the English or
the French path, keeps a document still to translate out of it. A stamp says that this English was read: stamp only
what was translated.

### While the existing packages catch up

`docs.translations` in `phil-dev.php` says whether a package's failures stop the gate: `blocking` or `advisory`. A
package's own value wins over the root's. Without either, the gate follows `docs.public`: a public package blocks,
any other is advisory, so that a new package passes before its French documentation is written. An advisory package
never fails: it is summed up on one line, and `-v` lists its findings.

To let a monorepo catch up, set the root to advisory:

```php
return [
    'docs' => ['translations' => 'advisory'],
];
```

The gate blocks at the release. `phil-dev new` writes no `translations` line: the new package is advisory until it is
split to its public repository. Its release sets `public`, which blocks on its own in a root without
`docs.translations`; under an advisory root, the release also turns the gate on in the package's own `phil-dev.php`:

```php
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
];
```

A package translated earlier may turn the gate on before its release with the same line. When the last package has
caught up, the key goes from the root.

The French follows [the glossary](fr-glossary.md): its words, its tone and what stays in English.
