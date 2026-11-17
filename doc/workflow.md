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
Twig-CS-Fixer on the templates, through `phil-dev staged`, `phil-dev normalize` for a `composer.json`,
`phil-dev check-docs` and `phil-dev check-translations` for a Markdown file.

No action of the hooks names a staged file. CaptainHook's `{$STAGED_FILES}` joins the names, unquoted, into a command
its shell runs: a file staged as `a; curl … | sh;.php` would run that command on the next commit of whoever pulled it.
`phil-dev staged` reads the names from git itself, separated by NUL bytes, and hands them to the tool as arguments of
their own, after `--`, so that a name with spaces, quotes, a semicolon or a leading dash stays one path. A project
that adds its own action keeps to fixed command lines, or to an action of CaptainHook's `php` type.
The slow gates — Deptrac, the tests, Infection — stay in `composer gates`. `composer hooks` installs the hooks once
per clone; a worktree shares them.

## The split

A member is finished when its `phil-dev.php` says where it goes:

```php
'split' => ['repository' => 'git@github.com:parler-haut-interagir-librement/slug.git'],
```

`phil-dev split [packages…]` splits the history of each finished member into one commit whose tree is the member's
directory, with `splitsh-lite` when it is on the `PATH`, else with `git subtree split`. A member without a
`SECURITY.md` is not split: the command says so and fails, and the other members go on.

On top of the split commit, a second commit adds the security files of a split repository, described under
[The security files of a split repository](#the-security-files-of-a-split-repository). That commit goes to the
repository as its branch: `split.branch`, else `--branch`, else `main`; `--tag=v1.0.0` also pushes it as that tag.

With `split.prefix`, the repository is another monorepo, such as the public one: it is cloned under
`var/phil-dev/split/`, the split commit is added under the prefix the first time and merged into it afterwards with
`git subtree`, and the branch is pushed. The local branch `phil-dev-split/<vendor>-<name>` points at the last commit
merged. No security commit is added there: GitHub reads `.github/` at the root of a repository only, and the target
monorepo has its own.

```php
'split' => ['repository' => 'git@github.com:parler-haut-interagir-librement/packages.git', 'prefix' => 'packages/slug'],
```

Nothing is pushed without `--push`: the command says what it would push. It never forces a push, so a branch that
moved in a way the split cannot follow, or a tag already published, makes the push fail instead of being overwritten.

### The security files of a split repository

The security commit adds two files to the tree of the split, from the templates of `resources/security/split/`:

| File | What it does |
|---|---|
| `.github/dependabot.yml` | Dependabot watches the `composer` dependencies, the `npm` ones when the member has a `package.json` at its root or under `assets/`, and the `github-actions` of the workflow below. |
| `.github/workflows/scorecard.yml` | The OpenSSF Scorecard of the repository, at each push to its branch and each week, published for the badge and uploaded to code scanning. |

The version updates of Dependabot open no pull request (`open-pull-requests-limit: 0`): a split repository is a
read-only copy, and a change made there would be lost at the next split. Its alerts, and the security updates when
the repository turns them on, still tell when a dependency has an advisory; the fix is made in the monorepo. The
Scorecard workflow pins each action to the commit of a version, named in a comment, and publishes its results with the
OIDC token of the workflow: it needs no secret and no account.

The commit is built with git's plumbing in the monorepo, through an index of its own under
`var/phil-dev/split/security-<vendor>-<name>/`, removed afterwards: no checkout, no branch, and nothing of the
history rewritten. Its tree is the split's with the two files added or replaced; its author and committer are the
split commit's committer, at the same date, and it is not signed. The same split and the same templates therefore
always give the same commit, which is how a dry run shows the commit the push would send.

Each split replays it without the histories drifting apart. The split itself is reproducible: `git subtree split` or
`splitsh-lite` gives the same commits for the same history of the member's directory, from the commit where it
arrived, and the next split descends from the previous one. With `--push`, the command first reads the tip of the
target branch with `git ls-remote` and fetches it:

- when the branch does not exist, or the split descends from its tip, the security commit has the split as its only
  parent;
- otherwise the tip — the security commit of the previous split — becomes its second parent, so the push is a
  fast-forward and every commit published before stays in the history;
- when the tip already has the tree the commit would have, the tip is pushed as it is, and nothing changes.

A dry run reads no remote, and shows the commit for a branch that does not exist yet.

### The badge and the files of the existing members

A split repository shows its score with the OpenSSF Scorecard badge, on its own line after the title of its README:

```markdown
[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/slug/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/slug)
```

The skeleton writes it in the README of a new package, and its French mirror, with the repository its vendor's
packages are split to: `parler-haut-interagir-librement` for `phil/*`, `TiltTek` for `tilttek/*`. `check-packages`
warns, without failing, about a member without a `SECURITY.md` or whose README has no security badge; an OpenSSF
Scorecard badge or a Snyk badge counts.

`phil-dev split --security-files [packages…]` splits nothing and pushes nothing. It adds to each member what it misses:
the `SECURITY.md` of the skeleton, and the badge after the title of its `README.md` and of its `README.fr.md`. The
repository of the badge is `split.repository` when it is on GitHub and the member has no prefix, else the one of its
vendor; a member of another vendor gets no badge. A French mirror that was up to date is stamped with the new README,
since the same line was added to both; a stale one stays stale. The changes are left in the working tree, to review
and commit.

### Snyk on a public monorepo

A public monorepo can also be tested by Snyk Open Source, on its free plan for open source. `resources/security/monorepo/`
holds the two files it needs:

| File | What it holds |
|---|---|
| `.snyk` | The policy: no ignore, and the form an ignore takes, with the reason, who decided it and when, and an expiry date after which Snyk reports the vulnerability again. |
| `snyk.yml` | A workflow for `.github/workflows/` that tests the `composer.lock` and the `package.json` files of the monorepo, leaving out `vendor-bin/`, `var/` and `node_modules/`, against the policy, failing from the high severity. |

The workflow does nothing until the repository has a `SNYK_TOKEN` secret, which a Snyk account gives; the repository
can be imported in Snyk's GitHub integration instead, which reads the same `.snyk`. The root's `split.snyk`, set to
`true` in the `phil-dev.php` of a public monorepo, makes `phil-dev split --security-files` copy both files to the root
when they are missing; a file already there is kept.

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
