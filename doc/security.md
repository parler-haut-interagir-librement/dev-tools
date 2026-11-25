# Security checks

Six checks look for what a vulnerability is made of: a dependency with an advisory, an input that reaches a page or a
query unescaped, a parser that breaks on an input nobody wrote, what a package brings with it, and what its split
repository shows of its supply chain.

| Check | Script | Runs |
|---|---|---|
| The advisories of the installed packages | `composer deps:audit` | in the gates |
| No package with an advisory ever installed | Composer's `policy.advisories.block` | at every `composer update` and `require` |
| The taint analysis of Psalm | `composer taint` | on its own, weekly and before each release |
| Fuzzing the parsers | `composer fuzz` | on its own, weekly and after a change to a parser |
| The bill of materials | `composer sbom` | on its own, before each release |
| Dependabot and the OpenSSF Scorecard of each split repository | `composer split` | at each split |

## The advisories

`phil-dev audit` runs `composer audit` on the root: every installed package, development ones included, against the
advisories of Packagist. An abandoned package is reported without failing.

Composer itself refuses to install a version with an advisory in the first place: `composer update`, `require` and
`remove` leave out every version an advisory of Packagist names, and fail when nothing else satisfies a constraint.
Composer 2.9 turned this on by default as `audit.block-insecure`; Composer 2.11 moved it to `policy.advisories.block`,
`audit.block-insecure` staying a deprecated fallback. The skeleton, and `phil-dev init`, write it in `composer.json`, so
that no global configuration turns it off for the package:

```json
"config": {
    "policy": {
        "advisories": {
            "block": true
        }
    }
}
```

`roave/security-advisories` is no longer needed in `require-dev`. A version kept on purpose goes in
`policy.advisories.ignore-id` with its advisory ID and the reason, never by setting `block` to `false`.

## The taint analysis

PHPStan stays the analyser of the gates. Psalm, in its own `vendor-bin/psalm/`, runs in taint analysis mode only: it
follows what comes from a request, a file or the environment to an HTML page, an SQL query, a shell, a file path or
`unserialize()`, through every call.

Psalm 7.15 or later follows SQL both ways: from a request to the query it reaches (`TaintedSql`), and from what a
query stores in a column to what another query reads back from it and prints (`TaintedHtml`, a stored XSS), through
`PDO`, `mysqli`, `pgsql` and `SQLite3`. Its Doctrine plugin, which Psalm ships with, models DBAL and the ORM: the
generated configuration loads it whenever the workspace installs `doctrine/dbal`, `doctrine/orm`,
`doctrine/persistence` or `doctrine/collections`, whatever the root's `composer.json` requires. The packages are
libraries: `markAllNonInternalCodeAsApi` treats every class not marked `@internal` as an API.

```php
$pdo->query("SELECT body FROM notes WHERE title = '".$_GET['title']."'"); // TaintedSql
$pdo->prepare('INSERT INTO notes (body) VALUES (?)')->execute([$_POST['body']]);
echo $pdo->query('SELECT body FROM notes')->fetch()['body'];             // TaintedHtml
```

`phil-dev taint` writes `var/phil-dev/psalm.xml` from the workspace: the source directories of every package analysed,
without the tests, at the lowest error level, since only the taint issues matter here. A manifest adds paths left out,
and the known, reviewed exceptions by issue type:

```php
'psalm' => [
    'excludes' => ['src/Generated'],
    // The renderer escapes every value itself; the raw output is its job.
    'suppress' => ['TaintedHtml' => ['src/Renderer/RawRenderer.php']],
],
```

### The host of the request

Besides the taints Psalm knows, the analysis follows `request_host`, the host a request names, which its client
chooses: `Request::getHost()`, `getHttpHost()`, `getSchemeAndHttpHost()`, `getUri()`, `getUriForPath()`, the host of
the router's request context, `UrlHelper::getAbsoluteUrl()` and the address of a login link; the `Host` and
`X-Forwarded-Host` headers and the server variables `HTTP_HOST`, `SERVER_NAME` and `HTTP_X_FORWARDED_HOST`, or a key
Psalm cannot tell; an address of the router that is absolute, a network path, or of a kind Psalm cannot tell. Its sinks
are the text and the HTML of an email, the template context of a templated email, and the content and the action of a
notification email; a Twig render and the signature of an address pass the taint on. A link built on it sends its
reader, and the token the link carries, to the host the client chose:

```php
$email->text('https://'.$request->getHost().'/reset?token='.$token); // TaintedCustom: request_host
$email->text($this->origin.$this->urls->generate('reset', ['token' => $token])); // passes
```

Psalm reports such a path as `TaintedCustom`. A link in an email is built on an address the configuration sets, never
on the request. The model is the stub `resources/psalm/request-host.phpstub`, which the generated `psalm.xml` names
with the custom taint.

## Fuzzing

A parser of markup, CSS, SVG, dice notation or JSON documents meets inputs nobody wrote. `nikic/php-fuzzer`, in its
own `vendor-bin/fuzzer/`, mutates a corpus of inputs towards the code they do not reach yet, and stops on the first
input that throws what the target does not expect.

A package declares its targets in its `phil-dev.php`, each a name and a PHP file:

```php
'fuzz' => [
    'targets' => ['markup' => 'tests/Fuzz/markup.php'],
    'budget' => 120,
    'corpus' => 'tests/Fuzz/corpus',
],
```

The target file loads the classes through `PHIL_DEV_AUTOLOAD`, which `phil-dev fuzz` sets, and gives the fuzzer its
target:

```php
<?php

declare(strict_types=1);

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        new Phil\Markup\Parser()->parse($input);
    } catch (Phil\Markup\Exception\ParseError) {
        // The error the parser promises for an input it cannot read.
    }
});
$config->setMaxLen(4096);
```

`phil-dev fuzz [targets…] [--budget=SECONDS] [--max-runs=N]` fuzzes each target for its budget: `--budget`, else the
manifest's `fuzz.budget`, else 60 seconds. The corpus of a target is `<corpus>/<name>`: its seeds, committed, and the
inputs the fuzzer adds when they reach new code, committed when they are worth keeping. A crash fails the run and
leaves its input in `var/phil-dev/fuzz/<name>/`; it becomes a unit test once the parser handles it.

### Strings drawn from a regular expression

A parser that validates by a regular expression — a slug, a phone number, an address, a dice notation — is tested on
its valid paths by strings drawn from that expression. `Phil\DevTools\Fuzz\RegexGenerator` reads the expression and
draws strings it must accept, with a fixed seed: the same seed draws the same strings on every PHP version, and a
failure found once is found again.

```php
$strings = new RegexGenerator(seed: 1)->generate(
    '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
    ['', '-slug', 'slug-', 'Slug', 'two--dashes'], // written by hand: the pattern must refuse each
    count: 64,
);

foreach ($strings as $slug) {
    self::assertSame($slug, Slug::fromString($slug)->toString());
}
```

The rejected cases are required: the drawn strings prove what the expression accepts, the cases written by hand what it
refuses, and `generate()` throws `RejectedCaseAccepted` when none is given or one is accepted. Each string drawn is
checked against the expression before it is returned.

| Read | Refused, with `UnsupportedPattern` |
|---|---|
| Character classes, ranges and negations; `\d`, `\w`, `\s` and `\D`, `\W`, `\S`; POSIX classes; `\p{L}`, `\p{Lu}`, `\p{Ll}`, `\p{N}`, `\p{Nd}` | Lookaheads and lookbehinds, atomic groups, conditions, recursion |
| `.`; escapes such as `\t`, `\n`, `\xhh`, `\x{hhhh}`, `\/` | Back-references, `\b` and `\B`, octal escapes, other letter escapes |
| Capturing, non-capturing and named groups; alternatives | Inline flags such as `(?i)` |
| `*`, `+`, `?`, `{n}`, `{n,}`, `{,m}`, `{n,m}`, greedy or lazy | Possessive quantifiers; a bound above 1000 |
| `^`, `$`, `\A`, `\z`, `\Z`; the modifiers `i`, `m`, `s`, `u`, `D`, `U`, `A`, `n` | The `x` modifier; a pattern above 4096 bytes or groups nested deeper than 64 |

`.` and the negated classes draw from printable ASCII, without both cases of a letter when the pattern ignores case;
the Unicode properties draw beyond ASCII under `u` only. An unbounded quantifier repeats at most eight times more than
its minimum, or what the second argument of the constructor sets. The generator is itself a fuzz target of this
package, `regex-generator`.

## The bill of materials

`phil-dev sbom [packages…]` writes `var/phil-dev/sbom/<vendor>-<name>.cdx.json` for each package: the CycloneDX 1.6
bill of materials of what installing it brings, without its development dependencies, made by
`cyclonedx/cyclonedx-php-composer` in its own `vendor-bin/sbom/`, reproducible from one run to the next.

A package with a `composer.lock` is read from it. A member of a monorepo has none: its `composer.json` is copied under
`var/phil-dev/sbom/work/`, with a path repository on each glob of the root's packages, and its dependencies are
resolved without being installed, which needs the network. The file is attached to each release.

## The split repositories

Each split repository gets, in a commit on top of its split, a Dependabot configuration and the OpenSSF Scorecard
workflow, whose score its README shows as a badge; a member without a `SECURITY.md` is not split. A public monorepo can
add Snyk Open Source, with a versioned `.snyk` policy whose every ignore is justified, dated and expires. See
[The monorepo workflow](workflow.md#the-security-files-of-a-split-repository).
