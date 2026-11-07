# {{package}}

**{{description}}**

{{intro}}

## Status

| Part | State |
|---|---|
| `{{bundle_class}}` and its configuration `{{alias}}` | Implemented and tested |

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
{{namespace}}\{{bundle_class}}::class => ['all' => true],
```

```yaml
# config/packages/{{alias}}.yaml
{{alias}}: ~
```

## Requirements

PHP {{php}} or later and Symfony {{symfony}} or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; {{holder}}. See [LICENSE](LICENSE).
