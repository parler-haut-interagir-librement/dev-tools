# Quality gates

`composer gates` runs them in this order, the order of a continuous integration job: the dependency analysis, the
advisories, the unused packages, the normalized `composer.json`, the code style of PHP, of the Twig templates and of
the scripts, PHPStan, Rector, Deptrac, Arkitect, the functional test applications, the diagnostics of Symfony Language
Tools, the tests and Infection. A gate is
in it when it is reliable and fast enough for every batch; the slower checks are scripts of their own.

| Check | Script | Where |
|---|---|---|
| Dependency analysis, advisories, unused packages, normalized `composer.json` | `deps`, `deps:audit`, `deps:unused`, `deps:normalize` | the gates |
| PHP-CS-Fixer, Twig-CS-Fixer, Biome | `cs`, `twig`, `js` | the gates |
| PHPStan and its witnesses, Rector, Deptrac, Arkitect | `phpstan`, `rector`, `deptrac`, `arkitect` | the gates |
| The container, the expressions and the YAML of each functional test application | `lint:apps` | the gates |
| The diagnostics of Symfony Language Tools on each Symfony application | `lsp` | the gates |
| PHPUnit through ParaTest, Infection | `test`, `infection` | the gates |
| TwigStan | `twigstan` | its own script, advisory: it reports and passes, `--strict` fails |
| The taint analysis, fuzzing, the bill of materials | `taint`, `fuzz`, `sbom` | their own scripts: see [Security checks](security.md) |
| The backward-compatibility check | `bc` | its own script: it installs two revisions |
| The benchmarks against their baseline | `bench:compare` | its own script: see [Benchmarks](benchmarks.md) |
| The prose of the documentation, the hotspots | `docs:lint`, `hotspots` | their own scripts: see [The monorepo workflow](workflow.md) |
| The file set and the links of the documentation | `docs` | its own script and the hooks: see [The package skeleton](skeleton.md#the-documentation-checks) |
| The French mirror of the documentation | `docs:translations` | its own script and the hooks: see [below](#the-french-mirror-of-the-documentation) |
| The conventions of a bundle configuration | `conventions` | its own script, warnings only, and `check-packages`: see [Commands](commands.md#check-config) |

The root's `phil-dev.php` turns off one of the gates `audit`, `unused`, `normalize`, `twig`, `js`, `apps` and `lsp` with
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

The configuration lets PHP-CS-Fixer run on a PHP newer than the syntax it declares: the next PHP of the matrix is
tested from its first release candidate, often before PHP-CS-Fixer supports it, and the code keeps to the syntax of
the floor, which PHP-CS-Fixer parses.

## Twig templates

`phil-dev twig` runs Twig-CS-Fixer on the templates of every package analysed: the directories of each manifest's
`twig.paths`, else `templates`, `src/Resources/views` and `Resources/views` when they exist. A workspace without
templates passes.

`phil-dev twigstan` compiles the templates to PHP and analyses them at the maximum level, with the types of the
context the PHP code renders them with. TwigStan runs its own PHPStan, without the extensions of the gates. The shared
Twig loader only knows the template directories: a package whose templates need its own extensions names, under
`twig.loader`, a PHP file that returns its `Twig\Environment`.

TwigStan is advisory. It is in no gate, and it passes whatever it finds, with a line that gives TwigStan's exit code:
without the Twig extensions of each bundle, the shared loader stops the analysis of their templates short of a verdict,
so a failure says nothing yet about the templates. `phil-dev twigstan --strict` returns TwigStan's own exit code.

## Scripts

`phil-dev js` runs Biome on the scripts of every package analysed — the Stimulus controllers — from the directories of
each manifest's `js.paths`, else `assets`: the recommended rules, and a format close to the PHP code style, four
spaces, single quotes and 120 columns. Biome is a single binary, without Node.js: the first run downloads it to
`var/phil-dev/bin/` through `kocal/biome-js-bundle`, at the version of the root's `js.biome` or the one this package
pins. A manifest's `js.rules` changes a rule by group.

Then, unless `--fix`, the gate runs the script tests of each package analysed that has a `tests/js` directory, with the
test runner of Node.js, from the package's directory:

```sh
node --import ./tests/js/register.mjs --test 'tests/js/**/*.test.mjs'
```

`--import` is given only when the package has `tests/js/register.mjs`, the module hooks that resolve the imports of its
controllers — `@hotwired/stimulus` and the like — to the stubs of its tests. The pattern is handed to Node.js as one
argument, without a shell, and Node.js expands it. A failure of Biome stops the gate before the tests; every package's
tests run, and the gate fails when one of them fails. Node.js must be on the `PATH` when a package has script tests: a
workspace with script tests and without `node` fails, one without script tests needs no Node.js.

## PHPStan

The maximum level with the strict rules, the uninitialised properties, the benevolent unions and the PHPDoc types
taken as certain, for every PHP of the matrix: `phpVersion` goes from the floor to the last release of the next
version. Every exception that no longer matches fails, except the shared ones of the tests. Bleeding edge brings the
checks of the next major version of PHPStan now. Each worker has 30 minutes for its share of the files
(`parallel.processTimeout`): a large monorepo analysed by `phil-dev php next` in Docker, through a bind mount, outlasts
PHPStan's 10 minutes on a busy machine. In that container, PHPStan writes its cache to directories of its own: see
[The version matrix](version-matrix.md#running-on-a-version).

Three extensions add to it:

- `phpstan-deprecation-rules`: a call to a deprecated class, method or function fails, so that the next version of a
  dependency removes nothing still used;
- `tomasvotruba/type-coverage`, at 100 %: every parameter, return, property and constant is typed natively, a PHPDoc
  type refining a native one, never replacing it;
- `tomasvotruba/cognitive-complexity`: 25 for a method, 100 for a class. A method above the bar is split, or carries
  its exception in its package's `phil-dev.php`, with the reason.

`config/phpstan-symplify.php` adds a selection of the rules of `symplify/phpstan-rules`, one by one: a class is
final or abstract, no global constant, exceptions in an `Exception` namespace, attributes in an `Attribute`
namespace, unique enum values, no narrowed visibility of a parent method, one class per file, no `static::CONSTANT`,
no method call on an array, no misnamed doc tag. `phpstan/extension-installer` ignores the package, which would load
every set. An interface stays where its code reads it best, beside its adapters or in a `Contract` namespace: no rule
picks one. An exception a `phil-dev.php` still declares for `symplify.requiredInterfaceContractNamespace` matches
nothing and fails nothing: the generated configuration gives it `reportUnmatched: false`, until the package drops it.

`config/phpstan.neon` loads the rules of this package described below: the calls to a PSR-3 logger, the phantom types
of a Doctrine mapping, the application secret as an HMAC key, the chained assignments, the private or static properties
declared again, and an array merged into itself in a loop.

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

### A log message is a constant with placeholders

`PsrLogContextRule` looks at each call to a PSR-3 logger, an object whose type is `Psr\Log\LoggerInterface` or a
class that implements it, through `emergency()` to `debug()` and `log()`, positional or named arguments. It reports
two things, each under its own identifier:

| Identifier | Reported |
|---|---|
| `phil.psrLogMessage` | A message built by interpolation (`"Order {$id}"`), concatenation (`'Order '.$id`) or `sprintf()` and `vsprintf()`, unless PHPStan knows every value it may take to be a literal. |
| `phil.psrLogContext` | An object other than a `Throwable` in the context, at any key, in nested arrays down to four levels, named by the keys that lead to it. |

A message built from its values is a new message for every value: a handler can no longer group the records of one
event, and the value sits where a processor that masks or drops a context key never sees it. An object in the context
is serialised however each handler sees fit, with whatever it holds, personal data included; PSR-3 names the
`Throwable` under `exception` as the one object a context carries. The fix is a constant message with placeholders,
and scalars, such as identifiers, in the context.

Refused, with the messages `The message of LoggerInterface::info() is built by interpolation: write a constant message
with placeholders, such as "Order {order} shipped.", and pass the values in the context.` and `The context of
LoggerInterface::warning() holds an object of class App\Entity\Order under "order": only a Throwable, under
"exception", may be an object.`:

```php
$logger->info("Order {$order->getId()} shipped.");
$logger->warning('Order {order} late.', ['order' => $order]);
```

Accepted:

```php
$logger->info('Order {order} shipped.', ['order' => $order->getId()]);
$logger->error('Order {order} failed.', ['order' => $order->getId(), 'exception' => $exception]);
$logger->debug('A message long enough to be split '.'over two literals.');
```

A nullsafe call, `$logger?->info()`, is checked as any other. A call through an unpacked argument list, a first-class
callable or a dynamic method name is left alone, as is a method of the same name on an object that is not a logger. A
message held in a variable is not followed: the rule reads the expression written at the call.

When a call must stay as it is, the exception names its identifier and its file in the package's `phil-dev.php`, with
the reason on the line above, and never as a `@phpstan-ignore` comment:

```php
'phpstan' => [
    'ignoreErrors' => [
        // The audit trail logs the subject as received; its normaliser masks the personal fields.
        ['identifier' => 'phil.psrLogContext', 'path' => 'src/Audit/AuditLogger.php'],
    ],
],
```

The witnesses `tests/Witnesses/PhpStan/LogMessage.php` and `LogContext.php` prove that the shared configuration still
loads the rule.

### A Doctrine mapping keeps clear of the phantom types

`DoctrineMappingTypeRule` reads the Doctrine attributes of each class as written, without loading them:

| Identifier | Reported |
|---|---|
| `phil.doctrineMutableDate` | A `#[Column]` typed `date`, `datetime`, `datetimetz` or `time`, by name or by `Types::DATE_MUTABLE`, `DATETIME_MUTABLE`, `DATETIMETZ_MUTABLE` or `TIME_MUTABLE`; a property typed `\DateTime`, declared or promoted, of an entity, an embeddable or a mapped superclass, or carrying a mapping attribute. |
| `phil.doctrineDecimal` | A `#[Column]` typed `decimal`, by name or by `Types::DECIMAL`. |

Doctrine tells a change by comparing the value it loaded with the value it finds: a `\DateTime` changed in place is
the same object, the change is never written, and an entity that shares the instance changes another behind its back.
A `decimal` column comes back as a string, which the first arithmetic turns into a float and its rounding. The forms
that hold are the immutable date types with `\DateTimeImmutable`, and an integer of minor units or a string column the
domain parses. The witnesses are `DoctrineMutableDate.php` and `DoctrineDecimal.php`.

### The application secret is never an HMAC key as it is

`HmacSecretKeyRule` looks at the key of `hash_hmac()`, `hash_hmac_file()`, `hash_init()`, `sodium_crypto_auth()`,
`sodium_crypto_auth_verify()` and `sodium_crypto_generichash()`, and reports `phil.hmacSecretKey` when the key is the
application secret as given: the literal `%kernel.secret%`, `getParameter('kernel.secret')`, or a variable, a property
or an array key named `secret`, `kernelSecret`, `appSecret` or `applicationSecret` — the names under which a bundle
receives `%kernel.secret%`.

Two bundles that key their digests with the same secret make digests that match: a pseudonym made by one recognises
the person behind a pseudonym made by the other, and a signature made for one purpose passes for another. Each use
derives its own key, under a label of its own:

```php
$this->key = hash_hkdf('sha256', $secret, 32, 'phil_forms.email_digest');

return hash_hmac('sha256', $email, $this->key);
```

A key that is not the application's, such as the seed of a TOTP or the shared secret of a webhook provider, is named
after what it is (`$seed`, `$signingKey`). The witness is `HmacSecretKey.php`.

### A chained assignment shares nothing

`ChainedAssignmentRule` reports `phil.chainedAssignment` on the outer assignment of a chain whose value is a `new`, as in
`$a = $b = new Cart()`, which gives both targets one object, or which writes a property with a `set` hook, as in
`$total = $order->total = $amount`, which gives `$total` the value written rather than the value the hook stored. One
assignment per target says what is meant. The witness is `ChainedAssignment.php`.

### A private or static property is not declared again

`InheritedPropertyRedeclarationRule` reports `phil.propertyRedeclaration` on a property, declared or promoted, that a
parent declares `private` or `static`. Neither is replaced: a private property declared again is a second property the
parent's methods never see, a static one a second value that `self::` in the parent and `static::` in the child read
apart. The witness is `PropertyRedeclaration.php`.

### An array is not merged into itself in a loop

`ArrayMergeInLoopRule` reports `phil.arrayMergeInLoop` on `$all = array_merge($all, $part)`, or
`array_merge_recursive()`, in a `for`, `foreach`, `while` or `do`: each turn copies the whole array, and n parts cost
n² copies. The parts are collected and merged once after the loop, `array_merge(...$parts)`, or appended with `[]=`. A
closure or a function inside the loop is not looked at, since the loop does not run it. The witness is
`ArrayMergeInLoop.php`.

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
| `phil.parseUrl` | `parse_url()` and `FILTER_VALIDATE_URL`: an address is parsed and validated by `phil/url`, on the WHATWG URL standard browsers follow | yes |
| `phil.processState` | `setlocale()`, `putenv()`, `date_default_timezone_set()`: what they set outlives the request in a worker; the locale goes to the intl formatters, the time zone to the date | yes |
| `phil.componentDispatcher` | the component's `EventDispatcherInterface` | no |

A **witness** is a file under `tests/Witnesses/PhpStan` that must fail: its docblock announces the identifier that
stops it, in backticks after `Expected identifier:`. `phil-dev witnesses` fails when PHPStan no longer reports that identifier
in that file, so that a rule that went silent fails the gate. A witness that announces `none` is the other half: what the
rules must accept, such as `filter_var($email, FILTER_VALIDATE_EMAIL)` or a key derived by `hash_hkdf()`; it fails when
PHPStan reports anything there, so that a rule grown too wide fails too. The witnesses sit beside the test suites, which
are allowed by name (`tests/Unit`, `tests/Integration`, `tests/Functional`, `tests/Support`, `tests/Fixtures`), so that
they stay under every rule. The rules of this package have their accepted and refused fixtures under
`tests/Fixtures/phpstan`, read by their unit tests.

## Rector

The PHP sets read from `composer.json`, the Symfony, Doctrine and PHPUnit sets of the installed versions, and the
prepared sets, without a version number written anywhere. It skips, in `Domain\Model` layers, the rule that would make
an entity `readonly`, and three rules whose choice the packages make the other way; from Rector 2.9 on, no set registers
the rules that would bring Doctrine collections or DBAL constants into a `Domain` layer. Rector reports, at the end of
the run, each skip that matched nothing
(`reportUnusedSkips()`): a package's `rector.skip` sheds the exceptions it no longer needs. The report fails nothing.

## Deptrac and Arkitect

Deptrac checks where the dependencies point — see [Dependencies](dependencies.md). Arkitect checks what a class is: every
class in a namespace of the packages; `Domain\Model` final and not readonly; `Domain\ValueObject` final and readonly;
`Domain\Repository` interfaces named `*Repository`; nothing of Doctrine in a domain but the mapping attributes, nor the
validator, the container, HTTP, Messenger, the serializer, forms or security; no ORM nor DBAL in an application layer;
no domain event that is a Messenger envelope. Each rule applies to `<Namespace>\<Layer>` and `<Namespace>\*\<Layer>`.

## The diagnostics of Symfony Language Tools

`phil-dev lsp` runs `symfony lsp:check`, through the Symfony CLI, on each Symfony application of the workspace: the
root and each member whose `composer.json` type is `project` and that has a `bin/console`, such as a site or a project
template. It reports what the language server reports in an editor: a route, a template, a service, a translation or
a Stimulus controller that does not exist, an unknown configuration key or option, a handler with the wrong signature.

Every code blocks. The command reads the codes `symfony lsp:check --list-codes` lists and gives them all to
`--fail-on`, and reads no baseline: an application starts clean and stays clean. Under GitHub Actions the report uses
the `github` format, which annotates the files. Arguments after `--` go to the tool; `--source-only` keeps it from
booting the application, which it does otherwise to index what the application registers at runtime.

A bundle or a library is no application: the tool reads only the files an application owns, and refuses the
functional test application of a bundle. Such a package is skipped with a note, even when it has a `bin/console`, and a
workspace without an application passes; `lint-apps` checks the test applications of the bundles. The Symfony CLI must
be on the `PATH`; the root's `'gates' => ['lsp' => false]` turns the gate off.

## The functional test applications

`phil-dev lint-apps` checks the small application each bundle boots for its functional tests, through that
application's own console. It finds the kernels under `tests/Functional/` of each package analysed: the concrete
classes whose parent's name ends with `Kernel`, read from the files without loading them. Each kernel boots in a process
of its own, in the `test` environment with debug on and `TMPDIR` under `var/tmp/` of the root, as for the tests, and
runs:

| Command | Checks |
|---|---|
| `lint:container` | every service: its arguments and their types, as the container compiles them |
| `lint:expressions` | the expressions of controller attributes, such as `#[IsGranted]`, and those the configuration lists, such as `allow_if` of `access_control` (Symfony 8.2) |
| `lint:yaml --check-schema` | the YAML files of the application's `config/` directory, against the JSON schema of its configuration |

The gate fails when one of them fails, or when the kernel does not boot. The tests boot the same kernels, but a service
no test asks for is never built: a wrong argument type, an expression with a missing function or a misspelt
configuration key reaches the gate before an application does. An expression `lint:expressions` reads is one a bundle
declares to the expression collector of Symfony 8.2.

A command the application does not have is skipped with a note: `lint:expressions` before Symfony 8.2, `lint:yaml`
without YAML files. Without `opis/json-schema`, which Symfony needs for `--check-schema`, `lint:yaml` checks the syntax
only and says so; a root that requires it in `require-dev` gets the schema check. A kernel whose constructor needs more
than `$environment` and `$debug` is skipped too, since no console could build it. A package leaves a kernel out by its
class name, with the reason, in its `phil-dev.php`:

```php
return [
    // The API Platform processor of Mercure is defined without Mercure installed.
    'apps' => ['skip' => ['Phil\ExampleBundle\Tests\Functional\App\OpenApiKernel']],
];
```

## PHPUnit

`Unit` runs without a kernel or a server; `Integration` boots a kernel or talks to a real server, and is skipped
without one — or replays its recordings, see [HTTP recordings](http-recordings.md). Deprecations, notices, warnings and
risky tests fail the run, on every PHP of the matrix (`failOnRisky`): a test that asserts nothing, prints, or leaves an
error or exception handler behind.

`phil-dev test` runs the suites with ParaTest, one process per core. Every test names what it covers, with
`#[CoversClass]` or `#[CoversNothing]` (`requireCoverageMetadata`): coverage counts only what a test sets out to test,
and Infection replays only the tests that cover a mutant. `ergebnis/phpunit-slow-test-detector` lists the tests above
half a second; a test slow on purpose says so with `#[MaximumDuration]`.

## Infection

Infection 0.35 runs from `vendor-bin/infection/`. The configuration is generated under `var/phil-dev/infection.json`:
the source directories of every package analysed, their excludes and the named exceptions of each manifest. The bar is
100 % of mutants killed and of covered mutants killed. A surviving mutant is a missing test, or code nothing can reach;
a mutant that no test can tell apart is named in the package's manifest with its reason, never excused by lowering the
bar.

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

Infection and its initial test run, which collects the coverage of the suite, run with the memory limit of the root's
`infection.memoryLimit`, `4G` by default: a monorepo suite of thousands of tests under coverage outgrows the limit of a
`php.ini`. The value is a limit of `php.ini`, digits with an optional `K`, `M` or `G`, or `-1` for none; anything else
stops the command. Infection then gives each mutant's run a limit drawn from what the initial run used.

```php
return [
    'infection' => ['memoryLimit' => '6G'],
];
```

`infection --changed` runs only the tests of the packages that hold the changed files: a file belongs to the member
whose directory holds it, else to the root. The others are left out of the PHPUnit configuration it generates, as
the planned packages are when they are skipped — their test directories removed from each suite and excluded, their
code excluded from the source —, so neither the initial run nor a mutant runs their tests, and only the changed
packages' code is covered. The root's own paths stay. A change to one member of a large monorepo runs that member's
tests, not the whole monorepo's.

Infection receives each changed file as an absolute path, one argument per file, after a `--` that keeps an option whose
value is optional, such as `--ignore-msi-with-no-mutations`, from taking the first file as its value. It reads a path
inside a source directory of its configuration as a file to mutate and any other existing path as a test path it hands
to PHPUnit, and it resolves a relative path against the directory of the configuration, `var/phil-dev/`; so only the
changed files inside the source directories of the packages analysed are passed. A file of a planned package that is
skipped, or outside the `infection.directories` of its package, is left out, and when none is left nothing is mutated. A
git command that fails — a ref git does not know, or a worktree whose repository git cannot reach — stops the command
with an error rather than passing for a change with nothing to mutate.

Infection reads the suites and the source of `var/phil-dev/phpunit/phpunit.xml` when the root's PHPUnit configuration
holds a glob: each `packages/*/src` or `packages/*/tests/Unit` is written as the list of directories it matches when
the configuration is generated. Infection rewrites the paths of the configuration it copies, so the list it reads does
not depend on how PHPUnit expands a glob from that copy, wherever the checkout lies.

`composer infection:changed` mutates the files added, modified or renamed since the point where the branch left the
base ref, committed or not, and the untracked files git does not ignore. The base, without `PHIL_DEV_BASE_REF`, is
whichever of `origin/main` and `main` the branch left last: the merges of a local `main` that is not pushed yet are
not changes of the branch. For the same files, the number of mutants is the same from one pass to the next; it moves
with the files themselves — a file created but not staged, one staged since, a rename, a base ref moved by a push or a
rebase — which is why each of these counts the same way. Under `--threads=max`, the share of mutants killed, timed out
or escaped may still move when the machine is busy: a mutant near its timeout is killed on one pass and timed out on
the next, and a package whose tests are slow on purpose gives its `infection.timeout`.

| When | On | Script |
|---|---|---|
| Once per batch, before its commit | the files changed since the base ref, on the floor PHP | `composer infection:changed` |
| Weekly and before each release | the whole code, on the floor PHP | `composer infection` |
| Weekly and before each release | the whole code, on the next PHP | `composer infection:next` |

A pass that reports mutants which "required more time than configured" has not judged them: Infection leaves out a
mutant whose covering tests took longer than the timeout in the initial run, and the covered MSI does not count it. A
busy machine or a coverage driver slows the initial run; the package whose tests are slow gives its
`infection.timeout`, and the pass runs again on a quieter machine.

### Passes side by side

Each checkout writes its configurations under its own `var/phil-dev/` and Infection's temporary files and logs under
its own `var/infection/`, so passes of `--changed` run at once from different worktrees; two passes from one checkout
overwrite each other's configuration. In the container of `phil-dev php`, a worktree's repository is mounted read-only
beside the project — see [The version matrix](version-matrix.md) —, so `--changed` lists the changed files of a
worktree there as on the host:

```sh
vendor/bin/phil-dev php floor -- php vendor/bin/phil-dev infection --changed
```

Infection starts one process per core but one with `--threads=max`: with two or three passes, each takes its share
of the cores, given after `--`.

```sh
vendor/bin/phil-dev infection --changed -- --threads=4
```

A pass narrowed to the packages of its changes needs little memory: the initial run of three bundles, 710 tests,
peaks under 300 MiB, far below `infection.memoryLimit`. The `4G` default stays for the full run, whose initial run
covers the whole monorepo. Each mutant's run gets a limit drawn from what the initial run used, so the memory of a
pass grows with its threads, not with the size of the monorepo.

On a monorepo of 233 packages and 40,057 Unit tests, with Xdebug as the coverage driver on an eight-core machine, a
change to three bundles runs the initial run of their 710 tests and its mutants in two to seven minutes, depending on
the load of the machine. Mutated with all the monorepo's tests and code, the same change had not finished its initial
run after 57 minutes. Two passes side by side, four threads each, took 2 minutes 14 seconds for the three bundles and
1 minute 13 seconds for another bundle, each under 220 MiB.

## The French mirror of the documentation

Every package is documented in English and in French. The English does not move: `README.md` and `doc/<name>.md`. The
French sits beside it, in `README.fr.md` and `doc/fr/<name>.md`, with the same names and the same tree. `phil-dev
check-translations`, or `composer docs:translations`, compares the two for each package; `PHIL_DEV_PACKAGES` or the
names given to it narrow them.

| Code | Means | Effect |
|---|---|---|
| `translation-missing` | An English document without its mirror. | Fails. |
| `translation-twin-missing` | A document at the root without its French twin. | Reported; fails where `docs.twins` is `blocking`, or with `--require-twins`. |
| `translation-orphan` | A mirror or a twin without its English document. | Fails. |
| `translation-headings` | Not as many headings of a level as the English, fenced code left out. | Fails. |
| `translation-code` | A fenced code block, info string included, that differs from the English, or not as many blocks. | Fails. |
| `translation-late` | The English has changed since the translation, or the mirror has no stamp. | Reported. |

A late translation whose structure has drifted fails too, on its headings or its code. `doc/fr-glossary.md`, written
in French, has no English document.

### The French twins at the root

The other documents at the root of a package — `CHANGELOG.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`
and any other `.md` — have their French twin beside them: `CHANGELOG.fr.md`, `CONTRIBUTING.fr.md`… So do the documents
at the root of a monorepo, its `README.md` included; they are checked with the members when no member is singled out by
name or by `PHIL_DEV_PACKAGES`. `AGENTS.md`, written for coding agents, and the licence have no twin.

A twin is compared with its English as a mirror is: its stamp, its headings and its code blocks. A twin still to write
is reported, without failing, on one line per package; `-v` lists each one:

```text
packages/example: no French twin of CHANGELOG.md, SECURITY.md
0 blocking, 0 late, 2 missing, 0 advisory.
```

`--require-twins`, or `docs.twins` set to `blocking` in the `phil-dev.php` of the package or of the root, makes each
one a failure of a blocking package:

```php
return [
    'docs' => ['twins' => 'blocking'],
];
```

`stamp-translations` stamps the twins of the packages it names with their mirrors, and those at the root of a
monorepo when it is named by `.` or by its Composer name:

```sh
phil-dev stamp-translations . --file=CHANGELOG.md
```

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
