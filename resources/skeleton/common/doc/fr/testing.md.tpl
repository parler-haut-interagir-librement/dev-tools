# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

Un test qui a besoin d'un vrai serveur — MySQL, PostgreSQL, Gotenberg — lit son URL dans une variable d'environnement
et est ignoré sans elle : `PHIL_TEST_MYSQL_URL`, `PHIL_TEST_POSTGRESQL_URL` et `PHIL_GOTENBERG_URL`.

Un test qui appelle un service HTTP est marqué `#[UseRecord]` et rejoue, hors ligne, le fichier HAR versionné à côté de
lui ; `SYMFONY_HTTP_RECORDER=record` l'enregistre de nouveau contre le vrai service, avec ses secrets masqués.
