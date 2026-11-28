<!-- phil-dev source-sha256=878d9f452a5d40d6309cb87c5339cfa7fa3d7b94d7c0b1f7327bd9b3e312046a -->
# Politique de sécurité

[English](SECURITY.md)

## Signaler une vulnérabilité

**Ne signalez pas une vulnérabilité dans un ticket, une demande de tirage ou une discussion publics.** Écrivez plutôt à
[security@ph-il.ca](mailto:security@ph-il.ca), avec :

- une description de la vulnérabilité et de ce qu'un attaquant peut en faire ;
- le paquet et la version ou le commit touchés ;
- les étapes pour la reproduire, ou une preuve de concept, si vous en avez une ;
- tout ce qui peut aider d'autre : des journaux, la configuration en cause, un correctif suggéré.

Vous recevrez un accusé de réception dans les 48 heures, puis des nouvelles du correctif à mesure qu'il avance. Gardez
la vulnérabilité confidentielle jusqu'à la publication d'une version corrigée ; nous conviendrons avec vous de la date
de divulgation et vous mentionnerons dans l'avis, à moins que vous préfériez ne pas être nommé.

## Comment une vulnérabilité est corrigée

- Un correctif est visé dans les 7 à 14 jours qui suivent l'accusé de réception, plus tôt pour une vulnérabilité grave.
- Le correctif est publié dans une nouvelle version du paquet, et annoncé dans ses notes de version et dans un avis de
  sécurité GitHub sur le dépôt du paquet.
- Un commit qui corrige une vulnérabilité commence par `security:`, pour que ceux qui ne peuvent pas mettre à niveau
  tout de suite puissent le trouver et l'appliquer.

## Versions prises en charge

Seule la dernière version de chaque paquet, sur sa branche principale, reçoit les correctifs de sécurité. Les paquets
sont en `0.x` : passez à la dernière version pour obtenir un correctif, ou appliquez vous-même son commit `security:`.

## Dépendances

Une vulnérabilité dans une dépendance se corrige en relevant la contrainte de la dépendance dès qu'une version corrigée
existe. Si vous trouvez une dépendance d'un paquet visée par un avis connu (`composer audit` les signale), vous pouvez
la signaler de la même façon.
