<!-- phil-dev source-sha256=c5c7ca5b657240367e91e4363fc79313246bb93458625263129fda313c8c3214 -->
# Installation

```sh
composer require tilttek/example-bundle
```

Enregistrez ensuite le bundle dans `config/bundles.php` :

```php
Tilttek\ExampleBundle\TilttekExampleBundle::class => ['all' => true],
```

Le paquet exige PHP 8.5 ou une version plus récente, et Symfony 8.2 ou une version plus récente.
