# phil/dev-tools

**Shared development tooling for PHIL and Tilttek packages: quality-tool configurations, Composer scripts,
documentation checks and the package skeleton.**

phil/dev-tools holds, once, what every PHIL and Tilttek package checks itself with: the configurations of
PHP-CS-Fixer, PHPStan, Rector, Deptrac, Arkitect, Infection, PHPUnit and PHPBench, the Composer scripts that run them,
the documentation checks, the standalone check of each package, the real servers of the integration tests, the HTTP
recordings that replay them offline, the matrix of PHP and Symfony versions, and the skeleton of a new package.

A package installs it in `require-dev` and runs `vendor/bin/phil-dev init`, which writes thin configuration files that
call the shared ones. Each package keeps its own paths and exceptions in a small `phil-dev.php`; in a monorepo, the
shared configurations find every package through its own files, so adding a package touches no file of the root and
several batches can work at once in their own worktrees.

## Status

| Part | State |
|---|---|
| Shared configurations, read through each package's `phil-dev.php` | Implemented and tested |
| `init`, `new`, `check-docs`, `check-packages`, `infection`, `deptrac`, `witnesses`, `generate` | Implemented and tested |
| Per-package allowed dependencies, generated into Deptrac | Implemented and tested |
| Real servers, HTTP recordings, member autoloading | Implemented and tested |
| The version matrix, `php` and `matrix`, `check-packages --symfony` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation and usage](doc/installation.md) | Installing, `init`, the Composer scripts, a new package. |
| [Commands](doc/commands.md) | Every command and option of `phil-dev`. |
| [Configuration](doc/configuration.md) | How the configurations are layered, and every section of `phil-dev.php`. |
| [Quality gates](doc/quality-gates.md) | What each gate checks, the PHPStan witnesses, the Infection modes. |
| [Dependencies](doc/dependencies.md) | The default every library may use, and what each package is allowed. |
| [Real servers](doc/services.md) | MySQL, PostgreSQL and Gotenberg in Docker, and their variables. |
| [HTTP recordings](doc/http-recordings.md) | Recording and replaying HTTP services with the recorder of Symfony 8.2. |
| [The version matrix](doc/version-matrix.md) | The PHP and Symfony versions, adding at RC1 and enforcing at x.y.3. |
| [The package skeleton](doc/skeleton.md) | What `new` writes, the wording, the CHANGELOG rule, the documentation checks. |
| [The add-on convention](doc/add-ons.md) | Tagged services, extension points and when they are stable. |
| [Extension points](doc/extension-points.md) | What a project may change in this package. |
| [Testing](doc/testing.md) | This package's tests, and the test helpers. |
| [Reference](doc/reference.md) | Every class and file of the package. |

## Quick start

```sh
composer require --dev phil/dev-tools
vendor/bin/phil-dev init
composer update
composer gates
```

## Requirements

PHP 8.5 or later; Docker for the real servers and the next PHP of the matrix.

## Contributing

Contributions are welcome: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
