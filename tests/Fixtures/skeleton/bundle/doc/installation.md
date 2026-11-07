# Installation

```sh
composer require tilttek/example-bundle
```

Then register the bundle in `config/bundles.php`:

```php
Tilttek\ExampleBundle\TilttekExampleBundle::class => ['all' => true],
```

The package needs PHP 8.5 or later and Symfony 8.2 or later.
