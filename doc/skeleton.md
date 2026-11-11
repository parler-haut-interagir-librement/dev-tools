# The package skeleton

`phil-dev new <vendor/name> [--bundle] [--planned]` writes the file set every package has, from the templates of
`resources/skeleton/`, modelled on the PHIL and Tilttek packages.

| File | Library | Bundle |
|---|---|---|
| `README.md` | the index of `doc/`, Status, Quick start, Requirements, Contributing, License | the same, with the bundle's configuration |
| `CHANGELOG.md` | one "Added" section | one "Added" section |
| `CODE_OF_CONDUCT.md`, `CONTRIBUTING.md`, `SECURITY.md`, `LICENSE` | the vendor's wording | the vendor's wording |
| `composer.json` | `library`, `"php": ">=` the floor | `symfony-bundle`, with `symfony/config` and `symfony/dependency-injection` at the floor |
| `composer-dependency-analyser.php`, `phil-dev.php` | yes | yes |
| `doc/` | installation, extension points, testing, reference | the same, and configuration |
| `README.fr.md`, `doc/fr/` | the French mirror of the README and of `doc/`, stamped with its English | the same |
| `src/`, `tests/Unit/` | empty | the bundle class on `AbstractBundle` and its test |

The versions come from the floor of the [version matrix](version-matrix.md), and the constraints allow the next one.
The `composer.json` requires `roave/security-advisories` in `require-dev`, which keeps every version with an advisory
out of an installation, and is written normalized, as the `deps:normalize` gate wants it.

`--title` sets the title of the README and of its mirror, the package name by default; `--description-fr` the French
description, the English one by default. `--requires=phil/geo,tilttek/dice` adds those packages to `require`, at
`^0.1@dev` unless a constraint follows a colon, and names them in `composer-dependency-analyser.php` as unused until
the code uses them.

## A planned package

`phil-dev new <vendor/name> --planned --description="…"` writes a package being written, which incubates with the
other members until its code is there:

| File | What `--planned` changes |
|---|---|
| `phil-dev.php` | `'status' => 'planned'`, which `--skip-planned` and `PHIL_DEV_SKIP_PLANNED=1` read; see [Configuration](configuration.md#planned-packages). |
| `README.md`, `README.fr.md` | The first sentence of the description in bold, then "Being written", the whole description of what the package will do, and a Status row for that code. |
| `doc/reference.md`, `doc/fr/reference.md` | For a library: the package is being written and has no public type yet. |
| `composer.json` | The first sentence of the description as its `description`. |
| `CHANGELOG.md` | A first "Added" entry: the description of what the package will do, while its code is being written. |
| `tests/Unit/` | For a library, `PackageTest`, the smoke test that its namespace is autoloaded from `src/`; a bundle's test of its bundle class is its smoke test. |

The package passes the documentation checks, the translation checks and its own tests as it is written. Once its code
is there, its `status` line goes, and its README and CHANGELOG describe the code instead of the plan.

## The wording

The documentation is in English, with its French mirror in `README.fr.md` and `doc/fr/`, which follows
[the glossary](fr-glossary.md); a change of the English updates the French in the same commit. A package never names
a site that uses it, nor the application it was first written for: what is specific to one application is
configuration. The licence holder is "Philippe Gamache, doing business as
Le Gnome Archiviste; a Parler Haut Interagir Librement project" for `phil/*`, and "…; a Tilttek project" for
`tilttek/*`, whose contribution guide says it follows the rules of the PHIL packages in their monorepo.

## The CHANGELOG

Until the product is launched publicly — until the packages are split to their public repositories —, a CHANGELOG keeps
one "Added" section, ordered from the core to the integrations, that describes the current state: a change is folded
into the entry it changes, never written as "Changed" or "Fixed". Once a package is public, its `phil-dev.php` sets
`'docs' => ['public' => true]` and the usual sections follow.

## The documentation checks

`phil-dev check-docs` checks, in each package:

- the file set: `README.md`, `CHANGELOG.md`, `CODE_OF_CONDUCT.md`, `CONTRIBUTING.md`, `LICENSE`, `SECURITY.md`,
  `composer-dependency-analyser.php`, `doc/extension-points.md`, and the `doc/`, `src/` and `tests/` directories;
- every relative link of the Markdown files, outside code blocks and code spans;
- every table row has as many cells as its header, an escaped pipe `\|` not a separator, and `**` pairs balance in
  each paragraph, code spans left out — a run of backticks closes only on a run of as many, as in CommonMark;
- the README lists every English document of `doc/` and has the Documentation, Requirements, Contributing and License
  sections, and `README.fr.md`, when there is one, lists every document of `doc/fr/`;
- the CHANGELOG rule above;
- the licence holder's wording of the vendor, in `LICENSE` and the README;
- the terms no package names: the regular expressions of the root's `docs.forbidden`, and `--forbid`.

The skeleton passes them as it is written, and those of `phil-dev check-translations`: see
[Quality gates](quality-gates.md#the-french-mirror-of-the-documentation).
