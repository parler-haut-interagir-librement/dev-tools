# {{package}}

**{{description}}**

{{intro_fr}}

## État

| Partie | État |
|---|---|
| `{{bundle_class}}` et sa configuration `{{alias}}` | Implémenté et testé |

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
{{namespace}}\{{bundle_class}}::class => ['all' => true],
```

```yaml
# config/packages/{{alias}}.yaml
{{alias}}: ~
```

## Prérequis

PHP {{php}} ou une version plus récente, et Symfony {{symfony}} ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; {{holder}}. Voir [LICENSE](LICENSE).
