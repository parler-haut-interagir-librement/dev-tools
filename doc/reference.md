# Reference

| Type | Role |
|---|---|
| `Console\Application` | `phil-dev` and its commands. |
| `Command\*Command` | One class per command; `WorkspaceCommand` gives them the root and the process runner. |
| `Workspace\Workspace`, `Package`, `PackageRole`, `Manifest` | The root, the members and the installed packages, with their `composer.json` and `phil-dev.php`. |
| `Workspace\MemberAutoloader` | Loads the members' classes from their own `composer.json`. |
| `Config\Tooling` | What the shared configurations of PHP-CS-Fixer, Rector, PHPStan and Arkitect read. |
| `Config\DeptracConfig`, `Layer`, `LayerKind` | The generated Deptrac configuration. |
| `Config\InfectionConfig` | The generated Infection configuration. |
| `Config\YamlWriter` | The YAML of the generated configurations. |
| `Matrix\Matrix`, `Version`, `VersionStatus` | The versions of PHP and Symfony of `config/matrix.json`. |
| `Init\Initializer`, `ComposerJson`, `InitResult`, `InitStatus` | What `init` writes. |
| `Skeleton\PackageName`, `SkeletonWriter` | What `new` writes. |
| `Docs\DocsChecker`, `Finding` | The documentation checks. |
| `Witness\WitnessChecker` | The witnesses of the PHPStan rules. |
| `Process\ProcessRunner`, `LocalProcessRunner`, `ProcessResult` | The processes the commands run. |
| `Services\TestServices` | The real servers and their variables. |
| `Testing\ServiceUrl`, `HttpRecording` | The helpers of integration tests. |
| `DevTools`, `Files` | Where this package is, and reading its files. |
| `Exception\DevToolsException` | Implemented by every exception of the package. |

| File | Role |
|---|---|
| `config/php-cs-fixer.php`, `rector.php`, `arkitect.php` | The shared configurations the thin files call. |
| `config/phpstan.neon`, `phpstan-disallowed.neon` and `.php`, `phpstan-workspace.php` | The shared PHPStan configuration. |
| `config/phpstan-witnesses.neon` and `.php` | The analysis of the witnesses. |
| `config/phpbench.json` | The shared PHPBench configuration. |
| `config/matrix.json` | The version matrix. |
| `resources/init/`, `resources/skeleton/` | The templates of `init` and `new`. |
| `resources/compose.yaml` | The real servers. |
| `resources/php/Dockerfile` | The image of a PHP of the matrix. |
| `resources/autoload-members.php` | The member autoloader, for a monorepo's `autoload-dev`. |
