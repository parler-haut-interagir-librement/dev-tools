# Contributing

This package is the shared development tooling of the open packages of
[Parler Haut Interagir Librement](https://github.com/parler-haut-interagir-librement) (PHIL) and of Tilttek. Open
issues and pull requests on its repository.

## Reporting a bug

- Check first that the bug is not already reported.
- Give the version of the package, of PHP and of the tool involved, the `phil-dev.php` and the command that show it.
- A security vulnerability is not a bug report: follow the [security policy](SECURITY.md) instead.

## Proposing a change

Open an issue before a large change, so that its design can be agreed first. Then:

1. **Write the code from scratch.** No code is copied from another project, even under a compatible licence:
   another project may serve as a reference to read, never as a source to copy, translate or port.
2. **Keep it independent of any application.** Its code, comments and documentation never name an application that
   uses it; what is specific to one project is configuration.
3. **Keep the versions in the matrix.** No script names a PHP or Symfony version: they are read from
   `config/matrix.json`.
4. **Test it.** Every change comes with its tests, and the code is held to 100 % mutation coverage.
5. **Document it, in English.** Update `doc/` and, when a topic is added, the documentation table of the README.
6. **Pass the checks.** `composer portes` runs every gate on this package through its own configurations, and
   `composer test:next` runs the tests on the next PHP of the matrix.

## Licence

By contributing, you agree to license your contribution under the MIT licence of the package, whose holder its
[LICENSE](LICENSE) names.

## Code of conduct

Everyone taking part follows the [code of conduct](CODE_OF_CONDUCT.md).
