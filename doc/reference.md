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
| `Config\CaptainHookConfig` | The `captainhook.json` `init` writes. |
| `Config\YamlWriter` | The YAML of the generated configurations. |
| `Matrix\Matrix`, `Version`, `VersionStatus` | The versions of PHP and Symfony of `config/matrix.json`. |
| `Init\Initializer`, `ComposerJson`, `InitResult`, `InitStatus` | What `init` writes. |
| `Skeleton\PackageName`, `SkeletonWriter` | What `new` writes. |
| `Docs\DocsChecker`, `Finding` | The documentation checks. |
| `Docs\TranslationChecker` | The checks of the French mirror, and whether a package's failures block. |
| `Docs\Mirrors` | Where the French mirror of a document lives, and which documents have one. |
| `Docs\SourceStamp` | The SHA-256 of the English source on the first line of a mirror. |
| `Docs\MarkdownStructure` | The headings by level and the fenced code blocks of a Markdown text. |
| `Witness\WitnessChecker` | The witnesses of the PHPStan rules. |
| `PhpStan\Rules\ClassNameMustEndWithComponentRule` | The PHPStan rule on the names of Twig and Live Components; see [below](#phpstan-rules). |
| `Process\ProcessRunner`, `LocalProcessRunner`, `ProcessResult` | The processes the commands run, with a timeout when one is given. |
| `Process\Executables` | The programs of the `PATH`: splitsh-lite, cspell, markdownlint-cli2, Vale, npx. |
| `Bench\WorkerBench`, `WorkerBenchResult` | The requests of the worker-mode benchmark and their percentiles. |
| `Services\TestServices` | The real servers and their variables. |
| `Testing\ServiceUrl`, `HttpRecording` | The helpers of integration tests. |
| `DevTools`, `Files` | Where this package is, reading its files and removing the directories it generates. |
| `Exception\DevToolsException` | Implemented by every exception of the package. |

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
| `resources/compose.yaml` | The real servers, and FrankenPHP for the worker-mode benchmark. |
| `resources/biome/download.php` | Downloads Biome through `kocal/biome-js-bundle`, without a Symfony kernel. |
| `resources/docs/words.txt`, `resources/docs/vale/Phil/` | The shared words of cspell, and the Vale style. |
| `resources/php/Dockerfile` | The image of a PHP of the matrix. |
| `resources/autoload-members.php` | The member autoloader, for a monorepo's `autoload-dev`. |

## PHPStan rules

The rules of this package run inside PHPStan, which loads them from `config/phpstan-symfony-ux.php`, part of the shared
`config/phpstan.neon` every project includes. They depend on nothing else at run time.

| Rule | Identifier | Requires |
|---|---|---|
| `ClassNameMustEndWithComponentRule` | `phil.componentClassName` | A class carrying `#[AsTwigComponent]` or `#[AsLiveComponent]` has a name ending with `Component`, with that exact case. |

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
