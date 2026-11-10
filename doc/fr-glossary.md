# Glossaire français-anglais

Ce glossaire fixe les mots et le ton de la documentation française des paquets `phil/*` et `tilttek/*`. La langue
source est l'anglais : le français suit `README.md` et `doc/`, dans `README.fr.md` et `doc/fr/`. Le français est celui
du Québec, neutre et technique : on le lit sans accent de région et sans jargon inutile.

## Le ton

- **On s'adresse au lecteur par « vous ».** « Enregistrez le bundle », jamais « enregistre » ni « on enregistre ».
- **Phrases simples et courtes.** Une idée par phrase, à la voix active, au présent. Coupez une longue phrase anglaise
  en deux plutôt que de la calquer.
- **Traduisez le sens, pas les mots.** « Ships with » devient « est fourni avec », pas « est expédié avec ».
- **Gardez ce que dit l'anglais, tout ce qu'il dit.** Ni ajout, ni explication de plus, ni omission. Un français qui
  dit autre chose que son anglais est un bogue.
- **Décrivez l'état actuel seulement.** Aucune mention de ce qui a changé, aucun historique de rédaction.
- **« L'application » désigne ce qui installe le paquet.** Comme en anglais, aucun site ni client n'est nommé.
- **Typographie.** Une espace avant le deux-points et le point-virgule, guillemets « » pour une citation en français,
  lignes de 120 colonnes au plus hors du code et des tableaux. Écrivez « 100 % » avec une espace.

## Ce qui ne se traduit jamais

- **Les blocs de code restent identiques à l'anglais**, commentaires compris : la porte les compare caractère par
  caractère.
- **Les identifiants de code restent tels quels** : classes, méthodes, options de configuration, commandes, variables
  d'environnement, chemins, étiquettes de service, tout ce qui est entre accents graves.
- **Le libellé du titulaire de la licence** reste en anglais, tout comme `LICENSE`, `CONTRIBUTING.md`, `SECURITY.md`,
  `CODE_OF_CONDUCT.md`, `CHANGELOG.md`, la `description` du `composer.json`, les commentaires et les docblocks.
- **Les noms propres** : Symfony, Doctrine, Messenger, Twig, Stimulus, PHPStan, Infection, UJO (Universal JSON Output).

## La structure

- **Chaque miroir a le même nom que son anglais** : `doc/installation.md` devient `doc/fr/installation.md`.
- **Autant de titres de chaque niveau** que l'anglais, dans le même ordre. Traduisez leur texte.
- **Les liens mènent aux fichiers français** quand ils existent : `doc/fr/installation.md` depuis `README.fr.md`,
  `reference.md` depuis `doc/fr/installation.md`, `../../README.fr.md` pour remonter au README. Un lien vers un fichier
  qui reste en anglais remonte à l'anglais : `../../CONTRIBUTING.md`.
- **La première ligne est l'empreinte de l'anglais.** Ne l'écrivez pas à la main. Une fois la traduction à jour,
  lancez `phil-dev stamp-translations <paquet>`, puis vérifiez avec `phil-dev check-translations <paquet>`.

## Les mots qui restent en anglais

| Terme | Remarque |
|---|---|
| *bundle* | Masculin : « le bundle ». |
| *cron* | |
| *claim check* | Le motif d'architecture. |
| *commit* | Masculin. Le verbe : « valider » un changement, ou « faire un commit ». |
| *handler* | Le *handler* d'un message de Messenger. |
| *linter* | Masculin. |
| *middleware* | Masculin. |
| *mock*, *stub* | Les doublures d'un test. |
| *nonce* | Masculin. |
| *slug* | Masculin. |
| *SKU* | Dans un identifiant ; dans le texte, *UGS* (unité de gestion de stock) à la première mention. |
| *voter* | Le *voter* de la sécurité de Symfony. |
| *webhook* | Masculin. |
| *widget* | Masculin. |
| *worker* | Masculin. Le mode *worker* de FrankenPHP, le *worker* de Messenger. |

## Du code et de l'architecture

| Anglais | Français | Remarque |
|---|---|---|
| aggregate | agrégat | |
| array | tableau | |
| attribute | attribut | Attribut PHP ou attribut HTML. |
| boolean | booléen | |
| callback | fonction de rappel | |
| class | classe | |
| command | commande | |
| constraint | contrainte | |
| container | conteneur | Le conteneur de services, un conteneur Docker. |
| controller | contrôleur | |
| dependency | dépendance | |
| deprecated | obsolète | « Marqué obsolète » ; jamais « déprécié ». |
| entity | entité | |
| envelope | enveloppe | Celle d'un message. |
| event | événement | |
| event dispatcher | répartiteur d'événements | |
| exception | exception | |
| extension point | point d'extension | |
| field | champ | |
| file | fichier | |
| form | formulaire | |
| handler (generic) | gestionnaire | Hors de Messenger. |
| interface | interface | |
| kernel | noyau | |
| lexer | analyseur lexical | |
| library | bibliothèque | Jamais « librairie ». |
| listener | écouteur | |
| locale | locale | Féminin : « la locale ». |
| message bus | bus de messages | |
| method | méthode | |
| migration | migration | |
| namespace | espace de noms | |
| package | paquet | |
| parser | analyseur | |
| port, adapter | port, adaptateur | |
| property | propriété | |
| provider | fournisseur | |
| query | requête | |
| repository (Doctrine) | dépôt | |
| request, response | requête, réponse | |
| route | route | |
| schema | schéma | |
| scope | portée | La portée d'une feuille de style : le sélecteur auquel chaque règle est limitée. |
| service tag | étiquette de service | |
| stamp | estampille | Celle d'une enveloppe de Messenger. |
| string | chaîne | |
| subscriber | abonné | |
| tag | étiquette | |
| template | gabarit | |
| transport | transport | |
| validator | validateur | |
| value object | objet-valeur | |

## De l'outillage et du travail en équipe

| Anglais | Français | Remarque |
|---|---|---|
| benchmark | banc d'essai | |
| branch | branche | |
| build | construction | Le verbe : « construire ». |
| bug | bogue | |
| checkout | copie de travail | Pour un dépôt git. |
| code style | style de code | |
| coverage | couverture | |
| default | valeur par défaut | |
| deployment | déploiement | Le verbe : « déployer ». |
| feature | fonctionnalité | |
| fixture | jeu de données d'essai | |
| floor | plancher | La plus basse version prise en charge. |
| fork | fourche | |
| gate | porte | Une porte de qualité. |
| git hook | crochet git | |
| hook | crochet | |
| hotspot | point chaud | |
| integration test | test d'intégration | |
| issue | ticket | |
| matrix | matrice | |
| merge | fusion | Le verbe : « fusionner ». |
| monorepo | monodépôt | |
| mutant, mutation testing | mutant, tests de mutation | |
| pull request | demande de tirage | |
| release | version | Le verbe : « publier ». |
| repository (git) | dépôt | |
| requirement | prérequis | |
| rollback | retour arrière | |
| sandbox | bac à sable | |
| seed | germe | Celui d'un générateur aléatoire. |
| server | serveur | |
| skeleton | squelette | |
| snapshot | instantané | |
| split | scission | Le verbe : « scinder ». |
| unit test | test unitaire | |
| witness | témoin | Un fichier que PHPStan doit refuser. |

## De la sécurité et de la vie privée

| Anglais | Français | Remarque |
|---|---|---|
| checksum | somme de contrôle | |
| claim | revendication | Celle d'un jeton. |
| consent | consentement | |
| cookie | témoin | « Témoin de connexion » à la première mention. |
| credential | identifiant de connexion | Au pluriel pour un ensemble. |
| decrypt | déchiffrer | |
| digest | empreinte | Résumé, pour un courriel périodique. |
| encrypt | chiffrer | Jamais « crypter ». |
| erasure | effacement | |
| fingerprint | empreinte | |
| firewall | pare-feu | |
| hash | empreinte | Le verbe : « calculer l'empreinte ». |
| key | clé | |
| lock | verrou | |
| password | mot de passe | |
| personal data | renseignements personnels | |
| privacy | vie privée | |
| rate limit | limite de débit | |
| salt | sel | |
| sanitize | assainir | |
| secret | secret | |
| signature, sign | signature, signer | |
| throttle | limiter le débit | |
| token | jeton | |
| vulnerability | vulnérabilité | |

## Du Web et de l'interface

| Anglais | Français | Remarque |
|---|---|---|
| backend | côté serveur | |
| breadcrumb | fil d'Ariane | |
| browser | navigateur | |
| cache | cache | Masculin. |
| chat | clavardage | Le verbe : « clavarder ». |
| dashboard | tableau de bord | |
| download | télécharger | |
| dropdown | liste déroulante | |
| email | courriel | |
| endpoint | point de terminaison | |
| escape | échapper | |
| feature flag | indicateur de fonctionnalité | |
| flag | indicateur | |
| font | police | |
| frontend | côté client | |
| layout | mise en page | |
| log, logger | journal, journaliseur | Le verbe : « journaliser ». |
| mailbox | boîte de courriel | |
| modal | fenêtre modale | |
| newsletter | infolettre | |
| payload | charge utile | |
| popover | bulle | |
| queue | file d'attente | |
| bounce | rebond | Pour un courriel. |
| render | produire le rendu | Le nom : « rendu ». |
| renderer | moteur de rendu | |
| retry | nouvelle tentative | |
| scheduler | planificateur | |
| session | session | |
| sidebar | barre latérale | |
| spam | pourriel | |
| stylesheet | feuille de style | |
| tenant | locataire | « Multilocataire » pour *multi-tenant*. |
| theme | thème | |
| time zone | fuseau horaire | |
| toggle | bascule | |
| tooltip | infobulle | |
| upload | téléverser | |
| website | site Web | |

## Du commerce

| Anglais | Français | Remarque |
|---|---|---|
| cart | panier | |
| checkout | passage à la caisse | Pour une commande. |
| coupon | coupon | |
| gift card | carte-cadeau | |
| invoice | facture | |
| order | commande | |
| payment | paiement | |
| point of sale | point de vente | |
| price | prix | |
| refund | remboursement | |
| shipping | livraison | |
| tax | taxe | |
| variant | variante | |
