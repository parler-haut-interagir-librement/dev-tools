# The monorepo workflow

Four tools serve the work around the code: the hooks before each commit, the split of the finished packages, the
prose of the documentation and the hotspots of the code.

| Script | What it runs | When |
|---|---|---|
| `composer hooks` | Installs the git hooks of `captainhook.json`. | Once per clone. |
| `composer split` | The finished packages split to their public repositories. | When a package is released. |
| `composer docs:lint` | cspell, markdownlint and Vale on the documentation. | Before a release of the documentation. |
| `composer hotspots` | The files that change most and are the most complex. | Before planning a refactoring. |

## The hooks

`init` writes `captainhook.json`. Before each commit, CaptainHook, in its own `vendor-bin/captainhook/`, runs the fast
gates on the staged files only, each when a file of its type is staged: PHP-CS-Fixer and PHPStan on the PHP files,
Twig-CS-Fixer on the templates, `phil-dev normalize` for a `composer.json`, `phil-dev check-docs` and
`phil-dev check-translations` for a Markdown file.
The slow gates — Deptrac, the tests, Infection — stay in `composer gates`. `composer hooks` installs the hooks once
per clone; a worktree shares them.

## The split

A member is finished when its `phil-dev.php` says where it goes:

```php
'split' => ['repository' => 'git@github.com:parler-haut-interagir-librement/slug.git'],
```

`phil-dev split [packages…]` splits the history of each finished member into one commit whose tree is the member's
directory, with `splitsh-lite` when it is on the `PATH`, else with `git subtree split`. The commit goes to the
repository as its branch: `split.branch`, else `--branch`, else `main`; `--tag=v1.0.0` also pushes it as that tag.

With `split.prefix`, the repository is another monorepo, such as the public one: it is cloned under
`var/phil-dev/split/`, the commit is added under the prefix the first time and merged into it afterwards with
`git subtree`, and the branch is pushed. The local branch `phil-dev-split/<vendor>-<name>` points at the last commit
merged.

```php
'split' => ['repository' => 'git@github.com:parler-haut-interagir-librement/packages.git', 'prefix' => 'packages/slug'],
```

Nothing is pushed without `--push`: the command says what it would push. It never forces a push.

## The prose of the documentation

`phil-dev lint-docs [packages…]` reads the `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `SECURITY.md`, `doc/` and
French mirror of each package with three linters, through the configurations it writes under `var/phil-dev/docs/`:

| Linter | What it checks | Configuration |
|---|---|---|
| cspell | The spelling. | English, or French for the mirrors, with the words of `resources/docs/words.txt` and of `docs.words`. |
| markdownlint | The Markdown. | The default rules, lines of 120 columns outside code and tables, `docs.markdownlint` over them. |
| Vale | The style of the English documentation. | The `Vale` style without its spelling, and the `Phil` style of `resources/docs/vale/`. |

The documentation of a package is in English unless its manifest says `'docs' => ['language' => 'fr']`, as the French
corpora do: cspell then reads the French dictionary as well, and Vale, whose rules are written for English, leaves it
out. The root's `docs.language` is the default of its members. Vale reads no French mirror either.

These linters are not PHP tools. Each is used when it is on the `PATH`; cspell and markdownlint-cli2 also through
`npx` when Node.js is installed. A linter missing is skipped with a note. `phil-dev check-docs` stays the gate.

The French dictionary of cspell is `@cspell/dict-fr-fr`, a development dependency of this package in its
`package.json`, at an exact version. The first time a French page is read, `lint-docs` looks for it in the
`node_modules/` of the root — this package's own while it is developed, after `npm install` — then in
`var/phil-dev/node/`. When neither has it and npm is on the `PATH`, it installs the pinned version there, without
scripts, lock file or audit, and the configurations of cspell import it by its absolute path. Without npm, cspell looks
for the package itself, as for a global install, and the command says so.

## The hotspots

`phil-dev hotspots` writes `var/phil-dev/churn.yml` and runs churn-php, in its own `vendor-bin/churn/`, on the
production code of every package analysed: the files that change often and are complex come first, the places where a
refactoring pays most. The root's `hotspots.files` shows more or fewer than ten files, and `hotspots.since` reads the
history from another date than one year ago. It reports and never fails.
