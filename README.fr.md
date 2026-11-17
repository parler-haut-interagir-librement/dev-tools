<!-- phil-dev source-sha256=8759616f7fe103bf1091f63f3e8eb5e867c622d140eba393222cec1acd3a23e9 -->
# phil/dev-tools

**Shared development tooling for PHIL and Tilttek packages: quality-tool configurations, Composer scripts,
documentation checks and the package skeleton.**

phil/dev-tools réunit, une seule fois, ce avec quoi chaque paquet PHIL et Tilttek se vérifie : les configurations de
PHP-CS-Fixer, de PHPStan et de ses extensions, de Rector, de Deptrac, d'Arkitect, d'Infection, de PHPUnit avec ParaTest
et de PHPBench, les portes des dépendances, des gabarits Twig et des scripts, les vérifications de sécurité — l'analyse
de propagation (*taint*), le *fuzzing*, les avis de sécurité et la nomenclature logicielle —, la vérification de la
compatibilité ascendante, les scripts Composer qui les lancent, les vérifications de la documentation, la vérification
autonome de chaque paquet, les vrais serveurs des tests d'intégration, les enregistrements HTTP qui les rejouent hors
ligne, les bancs d'essai, les crochets et la scission d'un monodépôt, la matrice des versions de PHP et de Symfony, et
le squelette d'un nouveau paquet.

Un paquet l'installe dans `require-dev` et lance `vendor/bin/phil-dev init`, qui écrit de minces fichiers de
configuration appelant les configurations partagées. Chaque paquet garde ses propres chemins et ses exceptions dans un
petit `phil-dev.php` ; dans un monodépôt, les configurations partagées trouvent chaque paquet par ses propres fichiers.
Ajouter un paquet ne touche ainsi aucun fichier de la racine, et plusieurs lots peuvent travailler en même temps, chacun
dans son *worktree*.

## État

| Partie | État |
|---|---|
| Configurations partagées, lues par le `phil-dev.php` de chaque paquet | Implémentées et testées |
| `init`, `new`, `check-docs`, `check-packages`, `infection`, `deptrac`, `witnesses`, `generate` | Implémentés et testés |
| Dépendances permises par paquet, générées dans Deptrac | Implémentées et testées |
| Vrais serveurs, enregistrements HTTP, chargement automatique des membres | Implémentés et testés |
| La matrice des versions, `php` et `matrix`, `check-packages --symfony` | Implémentés et testés |
| Les portes `audit`, `unused`, `normalize`, `twig` et `js`, ParaTest, les extensions de PHPStan | Implémentées et testées |
| `taint`, `fuzz`, `sbom`, `bc`, `twigstan` | Implémentés et testés |
| `bench --baseline` et `--compare`, `bench:worker` et le service FrankenPHP, l'image `bench-ext` | Implémentés et testés |
| `split`, la configuration de CaptainHook et `staged`, `lint-docs` et son dictionnaire français, `hotspots` | Implémentés et testés |
| Les fichiers de sécurité d'un dépôt scindé (Dependabot, OpenSSF Scorecard, zizmor), son badge, `split --security-files`, les fichiers Dependabot et zizmor d'un monodépôt, la politique Snyk d'un monodépôt public | Implémentés et testés |
| `lint-apps` : `lint:container`, `lint:expressions` et `lint:yaml` sur chaque application de test fonctionnel | Implémenté et testé |
| `lsp` : les diagnostics de Symfony Language Tools sur chaque application Symfony, chaque code bloquant | Implémenté et testé |
| `check-translations`, `stamp-translations`, le miroir français de `new` | Implémentés et testés |

## Documentation

| Document | Contenu |
|---|---|
| [Installation and usage](doc/installation.md) | L'installation, `init`, les scripts Composer, un nouveau paquet. |
| [Commands](doc/commands.md) | Chaque commande et chaque option de `phil-dev`. |
| [Configuration](doc/configuration.md) | L'empilement des configurations, et chaque section de `phil-dev.php`. |
| [Quality gates](doc/quality-gates.md) | Ce que vérifie chaque porte, les vérifications qui ont leur propre script, les témoins de PHPStan, les modes d'Infection, le miroir français. |
| [Security checks](doc/security.md) | Les avis de sécurité, l'analyse de propagation de Psalm, le *fuzzing*, la nomenclature logicielle. |
| [Benchmarks](doc/benchmarks.md) | PHPBench, les régressions par rapport à une référence, le mode *worker* de FrankenPHP. |
| [The monorepo workflow](doc/workflow.md) | Les crochets, la scission des paquets finis et leurs fichiers de sécurité, ceux d'un monodépôt, la prose de la documentation, les points chauds. |
| [Dependencies](doc/dependencies.md) | Ce que chaque librairie peut utiliser par défaut, et ce qui est permis à chaque paquet. |
| [Real servers](doc/services.md) | MySQL, PostgreSQL et Gotenberg dans Docker, et leurs variables. |
| [HTTP recordings](doc/http-recordings.md) | L'enregistrement et la relecture des services HTTP avec l'enregistreur de Symfony 8.2. |
| [The version matrix](doc/version-matrix.md) | Les versions de PHP et de Symfony, ajoutées à la RC1 et exigées au x.y.1 (mineure de Symfony) ou au x.y.3. |
| [The package skeleton](doc/skeleton.md) | Ce qu'écrit `new`, la formulation, la règle du CHANGELOG, les vérifications de la documentation. |
| [Le glossaire français](doc/fr-glossary.md) | Les mots et le ton de la documentation française, en français. |
| [The add-on convention](doc/add-ons.md) | Les services étiquetés, les points d'extension et le moment où ils sont stables. |
| [Extension points](doc/extension-points.md) | Ce qu'un projet peut changer dans ce paquet. |
| [Testing](doc/testing.md) | Les tests de ce paquet, et les outils d'aide aux tests. |
| [Reference](doc/reference.md) | Chaque classe et chaque fichier du paquet. |

## Démarrage rapide

```sh
composer require --dev phil/dev-tools
vendor/bin/phil-dev init
composer update
composer deps:normalize:fix
composer hooks
composer gates
```

## Prérequis

PHP 8.5 ou une version plus récente ; la CLI de Symfony pour la porte `lsp` ; Docker pour les vrais serveurs, FrankenPHP et la prochaine version de PHP de la
matrice ; git pour la scission ; cspell, markdownlint-cli2 et Vale, ou Node.js pour les deux premiers, pour vérifier la
prose de la documentation.

## Contribuer

Les contributions sont les bienvenues : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
