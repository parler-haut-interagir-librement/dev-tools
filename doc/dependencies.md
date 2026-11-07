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
