# tilttek/example-bundle

**An example bundle.**

tilttek/example-bundle integrates `tilttek/example` with Symfony. The library alone suits an application without Symfony.

## Status

| Part | State |
|---|---|
| `TilttekExampleBundle` and its configuration `tilttek_example` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Extension points](doc/extension-points.md) | The services to replace, the tags to add to, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, and the servers its integration tests read. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```php
// config/bundles.php
Tilttek\ExampleBundle\TilttekExampleBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_example.yaml
tilttek_example: ~
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
