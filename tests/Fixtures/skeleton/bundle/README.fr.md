<!-- phil-dev source-sha256=9187bc5dc3873563c8b2b21e33c913f5d322b062803b1542cb2541bfd93cdb9d -->
# tilttek/example-bundle

**An example bundle.**

tilttek/example-bundle intègre `tilttek/example` à Symfony. La bibliothèque seule convient à une application sans Symfony.

## État

| Partie | État |
|---|---|
| `TilttekExampleBundle` et sa configuration `tilttek_example` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les étiquettes à compléter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et les serveurs que lisent ses tests d'intégration. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Tilttek\ExampleBundle\TilttekExampleBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_example.yaml
tilttek_example: ~
```

## Prérequis

PHP 8.5 ou une version plus récente, et Symfony 8.2 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
