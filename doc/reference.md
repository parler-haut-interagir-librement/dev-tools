# Reference

| Type | Role |
|---|---|
| `Console\Application` | `phil-dev` and its commands. |
| `Command\*Command` | One class per command; `WorkspaceCommand` gives them the root and the process runner. |
| `Workspace\Workspace`, `Package`, `PackageRole`, `Manifest` | The root, the members and the installed packages, with their `composer.json` and `phil-dev.php`. |
| `Workspace\MemberAutoloader` | Adds the members' PSR-4 prefixes, from their own `composer.json`, to Composer's `ClassLoader`. |
| `Config\Tooling` | What the shared configurations of PHP-CS-Fixer, Rector, PHPStan and Arkitect read. |
| `Config\DeptracConfig`, `Layer`, `LayerKind` | The generated Deptrac configuration. |
| `Config\InfectionConfig` | The generated Infection configuration. |
| `Config\PsalmConfig` | The generated Psalm configuration of the taint analysis. |
| `Config\TwigStanConfig` | The generated TwigStan configuration and its PHPStan configuration. |
| `Config\BiomeConfig` | The generated Biome configuration and the Biome version. |
| `Config\ChurnConfig` | The generated churn-php configuration. |
| `Config\DocsLintConfig` | The generated configurations of cspell, markdownlint and Vale, and the files they read. |
| `Config\CaptainHookConfig` | The `captainhook.json` `init` writes: fixed command lines, the staged files read by `phil-dev staged`. |
| `Config\YamlWriter` | The YAML of the generated configurations. |
| `Matrix\Matrix`, `Version`, `VersionStatus` | The versions of PHP and Symfony of `config/matrix.json`. |
| `Init\Initializer`, `ComposerJson`, `InitResult`, `InitStatus` | What `init` writes. |
| `Skeleton\PackageName`, `SkeletonWriter` | What `new` writes. |
| `Docs\DocsChecker`, `Finding` | The documentation checks. |
| `Docs\TranslationChecker` | The checks of the French mirror, and whether a package's failures block. |
| `Docs\Mirrors` | Where the French mirror of a document lives, and which documents have one. |
| `Docs\SourceStamp` | The SHA-256 of the English source on the first line of a mirror. |
| `Docs\MarkdownStructure` | The headings by level and the fenced code blocks of a Markdown text. |
| `Docs\FrenchDictionary` | The French dictionary of cspell: its pinned version, where it is installed, the npm command that installs it. |
| `Workspace\TestKernels` | The kernels of a package's functional test applications, found by reading the files. |
| `Witness\WitnessChecker` | The witnesses of the PHPStan rules. |
| `PhpStan\Rules\ClassNameMustEndWithComponentRule` | The PHPStan rule on the names of Twig and Live Components; see [below](#phpstan-rules). |
| `PhpStan\Rules\PsrLogContextRule` | The PHPStan rule on the message and context of PSR-3 logger calls; see [below](#phpstan-rules). |
| `PhpStan\Rules\DoctrineMappingTypeRule`, `HmacSecretKeyRule`, `ChainedAssignmentRule`, `InheritedPropertyRedeclarationRule`, `ArrayMergeInLoopRule`, `LoopAssignmentFinder` | The PHPStan rules on Doctrine mappings, HMAC keys, chained assignments, properties declared again and merges in loops; see [below](#phpstan-rules). |
| `Conventions\ConfigurationConventions`, `ConfigurationTreeReader`, `RecipeReader`, `ConfigurationOption`, `TreeFrame`, `TreeFrameKind` | The conventions of a bundle configuration that `check-config` reports. |
| `Fuzz\RegexGenerator`, `Fuzz\Regex\*` | The strings a regular expression accepts, drawn with a fixed seed, for the fuzz targets and the tests; see [Security checks](security.md#strings-drawn-from-a-regular-expression). |
| `Process\ProcessRunner`, `LocalProcessRunner`, `ProcessResult` | The processes the commands run, without a shell, with a timeout when one is given, their error output captured with the standard one on request. |
| `Split\SecurityFiles` | The security files of a split repository and of a monorepo, the repository a package is split to, and its OpenSSF Scorecard badge. |
| `Split\ArchiveRules` | What the Composer archive of a package leaves out, from its `.gitattributes` and its `archive.exclude`, and what it must keep. |
| `Split\SecurityCommit` | The commit of the security files on top of a split commit, and the tip of the published branch it merges. |
| `Merge\MergeLock`, `LockHolder`, `LockTakeover` | The lock of the merges of a repository, what it says of the merge that holds it, and its takeover from a merge that stopped. |
| `Merge\MergeMessage`, `Worktrees`, `MergeFailure` | The message of the merge commit of a lot, the worktree of each branch, and the exit code of each step of `merge-lot`. |
| `Process\Executables` | The programs of the `PATH`: splitsh-lite, cspell, markdownlint-cli2, Vale, npx. |
| `Bench\WorkerBench`, `WorkerBenchResult` | The requests of the worker-mode benchmark and their percentiles. |
| `Services\TestServices` | The real servers and their variables. |
| `Testing\ServiceUrl`, `HttpRecording` | The helpers of integration tests. |
| `Testing\RouteSmokeTesting`, `SmokeRoutes` | The route smoke test of a test kernel, and the GET routes it requests; see [Testing](testing.md#route-smoke-test). |
| `Castor\Settings`, `Commands` | What the common Castor tasks read of `PHIL_CASTOR`, and the commands and coverage summaries they build and read; see [Castor tasks](castor.md). |
| `DevTools`, `Files` | Where this package is, reading its files and removing the directories it generates. |
| `Exception\DevToolsException` | Implemented by every exception of the package. |
| `Exception\InvalidCastorSettings` | A `PHIL_CASTOR` setting the common Castor tasks refuse. |
| `Exception\UnsupportedPattern`, `RejectedCaseAccepted` | A pattern the regex generator cannot draw from; a rejected case its pattern accepts, or none given. |

| File | Role |
|---|---|
| `config/php-cs-fixer.php`, `rector.php`, `arkitect.php` | The shared configurations the thin files call. |
| `config/phpstan.neon`, `phpstan-disallowed.neon` and `.php`, `phpstan-symplify.php`, `phpstan-symfony-ux.php`, `phpstan-workspace.php` | The shared PHPStan configuration. |
| `config/twig-loader.php` | The Twig environment TwigStan compiles with, when no package names its own. |
| `config/phpstan-witnesses.neon` and `.php` | The analysis of the witnesses. |
| `config/phpbench.json` | The shared PHPBench configuration. |
| `config/matrix.json` | The version matrix. |
| `resources/init/`, `resources/skeleton/` | The templates of `init` and `new`, the French mirror of the documentation included. |
| `doc/fr-glossary.md` | The words and the tone of the French documentation. |
| `resources/security/split/`, `resources/security/monorepo/` | The Dependabot and OpenSSF Scorecard templates of a split repository; the Snyk policy and workflow of a public monorepo. |
| `resources/castor/tasks.php` | The common Castor tasks an application's `castor.php` imports; see [Castor tasks](castor.md). |
| `resources/compose.yaml` | The real servers, and FrankenPHP for the worker-mode benchmark, on the plain image and on `bench-ext`. |
| `resources/bench-ext/Dockerfile` | The image of the benchmarks with PHP extensions, built on demand. |
| `resources/lint/app.php` | Lints one functional test application through its own console, for `lint-apps`. |
| `package.json` | The npm development dependencies: the French dictionary of cspell. |
| `resources/biome/download.php` | Downloads Biome through `kocal/biome-js-bundle`, without a Symfony kernel. |
| `resources/docs/words.txt`, `resources/docs/vale/Phil/` | The shared words of cspell, and the Vale style. |
| `resources/php/Dockerfile` | The image of a PHP of the matrix. |
| `resources/psalm/request-host.phpstub` | The model of the `request_host` taint of `phil-dev taint`. |
| `resources/autoload-members.php` | The member autoloader, for a monorepo's `autoload-dev`. |

## PHPStan rules

The rules of this package run inside PHPStan, which loads them from the shared `config/phpstan.neon` every project
includes: the rule on components through `config/phpstan-symfony-ux.php`, the others directly. They depend on nothing
else at run time, and read attributes and names as written, without loading their classes.

| Rule | Identifier | Requires |
|---|---|---|
| `ClassNameMustEndWithComponentRule` | `phil.componentClassName` | A class carrying `#[AsTwigComponent]` or `#[AsLiveComponent]` has a name ending with `Component`, with that exact case. |
| `PsrLogContextRule` | `phil.psrLogMessage` | The message of a PSR-3 logger call is not built by interpolation, concatenation, `sprintf()` or `vsprintf()`, unless its value is a literal. |
| `PsrLogContextRule` | `phil.psrLogContext` | The context of a PSR-3 logger call holds no object other than a `Throwable`, down to four levels of nested arrays. |
| `DoctrineMappingTypeRule` | `phil.doctrineMutableDate` | A Doctrine mapping has no mutable date type (`Types::DATE_MUTABLE`, `DATETIME_MUTABLE`, `DATETIMETZ_MUTABLE`, `TIME_MUTABLE` or their names) and no mapped `\DateTime` property. |
| `DoctrineMappingTypeRule` | `phil.doctrineDecimal` | A Doctrine mapping has no `decimal` column. |
| `HmacSecretKeyRule` | `phil.hmacSecretKey` | The application secret is not the key of an HMAC as it is: `hash_hkdf()` derives one per use. |
| `ChainedAssignmentRule` | `phil.chainedAssignment` | A chained assignment neither gives one new object to several targets nor passes on the value written to a property with a `set` hook. |
| `InheritedPropertyRedeclarationRule` | `phil.propertyRedeclaration` | A class does not declare again a property a parent declares `private` or `static`. |
| `ArrayMergeInLoopRule` | `phil.arrayMergeInLoop` | A loop does not merge an array into itself with `array_merge()` or `array_merge_recursive()`. |

A suffix tells the role of a class at a glance, as `Interface`, `Trait` and `Abstract…` do, and keeps a name clear of
the words PHP reserves: `ListComponent` is a valid class name, `List` is not. The rule replaces
`ClassNameMustNotEndWithComponentRule` of `kocal/phpstan-symfony-ux`, which asks the opposite and is no longer loaded.
It compares the resolved name of the attribute without loading its class, so a project without Symfony UX runs it
safely.

```php
// Refused: Class Alert carries #[AsTwigComponent]: its name must end with "Component", such as AlertComponent.
#[AsTwigComponent('Alert')]
final class Alert
{
}

// Accepted.
#[AsTwigComponent('Alert')]
final class AlertComponent
{
}
```

See [Quality gates](quality-gates.md#a-components-name-ends-with-component) for the details.

A log message built from its values is a new message for every value, which a handler cannot group and a processor
cannot mask; an object in the context is serialised however each handler sees fit. The logger is recognised by its
type, compared by name, so a project without `psr/log` runs the rule safely.

```php
// Refused: phil.psrLogMessage, then phil.psrLogContext.
$logger->info("Order {$order->getId()} shipped.");
$logger->warning('Order {order} late.', ['order' => $order]);

// Accepted.
$logger->info('Order {order} shipped.', ['order' => $order->getId()]);
$logger->error('Order {order} failed.', ['order' => $order->getId(), 'exception' => $exception]);
```

See [Quality gates](quality-gates.md#a-log-message-is-a-constant-with-placeholders) for the details and the way to
declare an exception.
