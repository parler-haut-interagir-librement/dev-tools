# Dependencies

Which code may use what is decided per package, in its own `phil-dev.php`, from a shared default. `phil-dev deptrac`
generates the Deptrac configuration from those files.

## The default

Every library and every domain layer may use, without asking:

- the Symfony components and contracts (`Symfony\Component\…`, `Symfony\Contracts\…`) and the polyfills;
- the PSR interfaces (`Psr\…`).

A library uses an existing Symfony component rather than reinventing what it does: a slug through
`symfony/string`, a date through `symfony/clock`, a YAML file through `symfony/yaml`. The package still requires the
component in its `composer.json`, which the dependency analysis checks.

A library never depends on a bundle — neither a `Symfony\Bundle\…` class nor the layers of a bundle of the monorepo.

The root's `deptrac.default` replaces the default for a project with a narrower rule of its own.

## What a package is allowed

Any other dependency is listed by the package:

```php
return [
    'deptrac' => ['allow' => ['^League\\\\Uri\\\\.*', '^Egulias\\\\EmailValidator\\\\.*']],
];
```

Each expression becomes a layer, `AllowedLeagueUri`, that only the packages listing it may see; everything else falls in
`Forbidden`. **Adding a dependency outside a library's list is a decision to ask for**, not a line to add on the way:
the list is the record of what each library was allowed, and why it is short.

## The default rules

| Layer | Sees |
|---|---|
| A library | the default, its allowed dependencies, and the libraries it requires |
| A bundle's `Domain` | the same, and the domains of the bundles it requires |
| A bundle's `Application` | its domain, the default, its allowed dependencies, `Forbidden`, and all but the infrastructure of what it requires |
| A bundle's `Infrastructure` and bundle class | everything above and everything it requires, `require-dev` included |

The application and the infrastructure of a bundle integrate Symfony, Doctrine and the rest, so they see `Forbidden`.

A package replaces the rules of one of its layers with `deptrac.ruleset`, and its layers with `deptrac.layers`; a rule
naming a layer that does not exist is dropped. Two packages may not declare the same layer.

## Optional integrations

A bundle often works with a package it does not require: it lists it under `suggest` in its `composer.json` and
registers what uses it only when the application has installed it. The code that touches such a package lives in
`src/Integration/<Package>/`, and nowhere else:

```text
src/
├── Domain/
├── Application/
├── Infrastructure/
├── Integration/
│   └── Outbox/
│       └── OutboxIndexingListener.php
└── ExampleBundle.php
```

A bundle with an `Integration` directory in one of its namespaces gets a fifth layer, `<Name>Integration`, and its
suggested packages are taken out of reach of the rest of its code:

| Layer | Sees |
|---|---|
| A bundle's `Integration` | everything its infrastructure sees, its infrastructure, and the packages it suggests |
| The bundle class | everything above, its integration and the packages it suggests |
| Its `Domain`, `Application` and `Infrastructure` | neither the integration nor the packages it suggests, even those of `require-dev` |
| Any other package | never that integration |

The bundle class is the wiring: it checks that the package is installed with `class_exists(Outbox::class)` and
registers the classes of the integration then. Every class is named by `::class`, never by a string: PHPStan checks the
name, the IDE renames it, and Rector's `StringClassNameToClassConstantRector` needs no exception.

What counts as a suggested package:

- a package of the workspace — a member of the monorepo, or an installed package that ships a `phil-dev.php` — is seen
  through its own layers;
- any other package is read from `vendor/<name>/composer.json` of the root: each of its PSR-4 namespaces becomes a
  layer, `Suggested` and the namespace without backslashes (`Loupe\Loupe` → `SuggestedLoupeLoupe`), taken out of
  `Forbidden`; every other layer that sees `Forbidden` sees it too. A package that is not installed has no layer:
  install it in `require-dev` so that the tests and the check reach it;
- a package the bundle also requires, and a namespace the default or an `allow` already covers — a Symfony component,
  a PSR interface — are left where they are.

The manifest decides instead of the directory:

```php
return [
    // true: the layer exists before the first integration is written; false: not yet, while the code moves there.
    'deptrac' => ['integrations' => true],
];
```

A package whose layers `deptrac.layers` declares, and a library, have no integration layer. A rule of the bundle class
or of the integration that `deptrac.ruleset` replaces, and that names `Forbidden`, still sees the packages the bundle
suggests.

The dependency analysis sees the same code: the package of an integration is suggested and installed in development
only, which its `composer-dependency-analyser.php` accepts for the integration and the bundle class:

```php
->ignoreErrorsOnPath(__DIR__.'/src/Integration', [ErrorType::DEV_DEPENDENCY_IN_PROD])
->ignoreErrorsOnPackages(['phil/outbox'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
```
