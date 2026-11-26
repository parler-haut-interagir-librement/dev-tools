# The monorepo workflow

Five tools serve the work around the code: the hooks before each commit, the merge of a lot into main, the split of
the finished packages, the prose of the documentation and the hotspots of the code.

| Script | What it runs | When |
|---|---|---|
| `composer hooks` | Installs the git hooks of `captainhook.json` in the git directory the worktrees share. | Once per clone. |
| `phil-dev hooks --check` | Checks that a checkout has the tools the hooks call. | In a new worktree, before its first commit. |
| `phil-dev merge-lot <branch>` | The branch of a lot rebased, gated and merged into main, then its worktree and branch removed. | When a lot is finished. |
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
The slow gates — Deptrac, the tests, Infection — stay in `composer gates`.

### Hooks and worktrees

`composer hooks` runs `phil-dev hooks`, once per clone. The hooks live in the git directory that the clone and all its
worktrees share, and each runs from the checkout that commits: CaptainHook, then the tool of each action, from that
checkout's own `vendor-bin/`. So:

- `phil-dev hooks` installs them with CaptainHook's `--git-directory` set to that shared directory, from the main
  checkout as from a worktree. Run from a worktree without it, CaptainHook would take `.git` — a file there — for the
  directory of the hooks;
- before installing, it checks that the checkout has CaptainHook, at the `config.run.path` of `captainhook.json`, and
  the tool under `vendor-bin/` of each action that runs `phil-dev staged <tool>`, and names the
  `composer bin <tool> install` of each one missing;
- a new worktree needs those tools before its first commit. `composer install` installs them, through the
  `forward-command` of the Composer bin plugin; a worktree whose `vendor/` is copied from the main checkout instead
  copies the `vendor/` of each of `vendor-bin/captainhook/`, `vendor-bin/php-cs-fixer/`, `vendor-bin/phpstan/` and
  `vendor-bin/twig/` as well, or runs `composer bin all install`. `phil-dev hooks --check` says whether it is ready.

A commit never goes around the hooks — no `--no-verify`, no `core.hooksPath` set aside: a checkout where they cannot run
gets its tools first.

### The hooks of a monorepo

A monorepo uses the `captainhook.json` that `init` writes, as a package does, with `vendor/bin/phil-dev` as the binary:

```json
{
    "config": {
        "ansi-colors": true,
        "fail-on-first-error": false,
        "run": {"mode": "shell", "path": "vendor-bin/captainhook/vendor/bin/captainhook"}
    },
    "pre-commit": {
        "enabled": true,
        "actions": [
            {"action": "php vendor/bin/phil-dev staged php-cs-fixer", "conditions": [{"exec": "\\CaptainHook\\App\\Hook\\Condition\\FileStaged\\OfType", "args": ["php"]}]},
            {"action": "php vendor/bin/phil-dev staged phpstan", "conditions": [{"exec": "\\CaptainHook\\App\\Hook\\Condition\\FileStaged\\OfType", "args": ["php"]}]},
            {"action": "php vendor/bin/phil-dev staged twig-cs-fixer", "conditions": [{"exec": "\\CaptainHook\\App\\Hook\\Condition\\FileStaged\\OfType", "args": ["twig"]}]},
            {"action": "php vendor/bin/phil-dev normalize", "conditions": [{"exec": "\\CaptainHook\\App\\Hook\\Condition\\FileStaged\\OfType", "args": ["json"]}]},
            {"action": "php vendor/bin/phil-dev check-docs", "conditions": [{"exec": "\\CaptainHook\\App\\Hook\\Condition\\FileStaged\\OfType", "args": ["md"]}]},
            {"action": "php vendor/bin/phil-dev check-translations", "conditions": [{"exec": "\\CaptainHook\\App\\Hook\\Condition\\FileStaged\\OfType", "args": ["md"]}]}
        ]
    }
}
```

`init` writes it when it is missing. An older one is replaced by this content, and the `hooks` script by
`@php vendor/bin/phil-dev hooks`: `init --force` would do both, but it rewrites every thin file of the root with them.
A script of the monorepo's own that runs a tool on
the staged files gives way to `phil-dev staged`, which does the same from git, without a shell; `staged phpstan` passes
when every staged file is outside what PHPStan analyses, so `--in-dir=packages/` is only needed to keep the root's own
files out. The hooks then need, in each checkout, `vendor-bin/captainhook/`, `vendor-bin/php-cs-fixer/`,
`vendor-bin/phpstan/` and `vendor-bin/twig/`, each with its `composer.json` and `composer.lock` committed.

## The merge of a lot

A lot is a branch worked on in a worktree of its own, beside the checkout of main. `phil-dev merge-lot <branch>`,
run from the checkout of main, merges it in one go and stops at the first step that fails:

| Step | What it does | Exit code when it fails |
|---|---|---|
| Lock | Creates `.git/fusion-lot.lock` in the common git directory, with the PID, the start time, the lot and the branch. | 3 |
| Clean | Checks that no tracked file is modified and no rebase is in progress, in the checkout of main and in the worktree, and that no merge is in progress in main. | 4 |
| Rebase | `git rebase <main>` in the worktree, then `composer install` there when a `composer.lock` changed. | 5, 6 |
| Gates | Each script of the worktree's `gates` script, in order, Infection left out; the full PHPStan is among them, or runs last when the gates do not name it. | 7 |
| Merge | `git merge --no-ff` of the rebased tip, with the message below. | 8 |
| Check | The merge commit: its parents are main before the merge and the tip of the branch, its subject is « Fusion du lot <lot> ». | 9 |
| Install | `composer install` in the checkout of main when the merge changed `composer.lock` or a `vendor-bin/*/composer.lock`. | 6 |
| Cleanup | `git worktree remove`, then `git branch -d`. | 10 |

Exit code 2 means there is nothing to merge: not a git repository, the checkout not on main, no such branch, no
worktree of its own for it, or a branch main already holds without its merge commit. A failed rebase or merge is
aborted, so that the worktree and main stay as they were; whatever step fails, nothing is removed and the lock is
released. The worktree is only removed once the merge commit is checked, and `git branch -d` refuses a branch that is
not merged.

The message of the merge commit names the lot, the branch without its `b/` prefix unless `--lot` names it, then one
bullet per commit of the branch, oldest first:

```text
Fusion du lot r72-merge-lot

- Commande merge-lot
- Tests : reprise du verrou
```

One merge runs at a time per repository. The lock is touched at each step; another merge finds it held and stops with
exit code 3, unless its process is gone (`ps` finds no such PID) or it has not moved for 30 minutes, in which case it
takes it over and says whose lock it took and why. Without `ps` on the `PATH`, only the 30 minutes count. A takeover of
the same lot runs its merge again from the start: an unfinished `git merge` of that branch left in the checkout of main
is aborted first. A takeover of another lot says which branch to merge again afterwards. Running the command again on a
branch already merged by its merge commit only removes its worktree and branch, after `composer install` when that merge
changed a lock file.

The gates run with `TMPDIR` under `var/tmp/` of the worktree, and Composer is the one running the script when the
command runs from a Composer script, else the one on the `PATH`. `--main` names another branch to merge into.
What the command reads of its lock and of `git worktree list` is fuzzed by the `merge-lock` target of this package.

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

The security commit adds three files to the tree of the split, from the templates of `resources/security/split/`:

| File | What it does |
|---|---|
| `.github/dependabot.yml` | Dependabot watches the `composer` dependencies, the `npm` ones when the member has a `package.json` at its root or under `assets/`, and the `github-actions` of the workflow below. |
| `.github/workflows/scorecard.yml` | The OpenSSF Scorecard of the repository, at each push to its branch and each week, published for the badge and uploaded to code scanning. |
| `.github/workflows/zizmor.yml` | zizmor audits the workflows of the repository, at each push to its branch, each pull request and each week: injection through untrusted inputs, credentials left to the checkout, permissions wider than needed, actions not pinned to a commit. Its findings fail the job and annotate the workflows. |

The version updates of Dependabot open no pull request (`open-pull-requests-limit: 0`): a split repository is a
read-only copy, and a change made there would be lost at the next split. Its alerts, and the security updates when
the repository turns them on, still tell when a dependency has an advisory; the fix is made in the monorepo. Each
update waits seven days after a release (`cooldown`), as in the monorepo, which zizmor requires. The
Scorecard workflow pins each action to the commit of a version, named in a comment, and publishes its results with the
OIDC token of the workflow: it needs no secret and no account.

The commit is built with git's plumbing in the monorepo, through an index of its own under
`var/phil-dev/split/security-<vendor>-<name>/`, removed afterwards: no checkout, no branch, and nothing of the
history rewritten. Its tree is the split's with the three files added or replaced; its author and committer are the
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

### The security files of a monorepo

`phil-dev split --security-files`, run at the root of a monorepo, also copies to the root, from
`resources/security/monorepo/`, the files it misses; a file already there is kept:

| File | What it does |
|---|---|
| `.github/dependabot.yml` | Dependabot watches the `composer` dependencies of the root and of each `vendor-bin/` directory, the `npm` ones of the root, of each member and of its `assets/`, and the `github-actions`. A release waits seven days before Dependabot proposes it (`cooldown`), so that a compromised or broken release is usually withdrawn first; the security updates do not wait. The updates come grouped: Symfony, then the other development and production dependencies, then every action. |
| `.github/workflows/zizmor.yml` | zizmor audits the workflows of the monorepo, as in a split repository, at each push to `main`, each pull request and each week. |

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
