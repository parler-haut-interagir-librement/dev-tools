<?php

declare(strict_types=1);

use Phil\DevTools\Config\Tooling;
use Rector\CodeQuality\Rector\Concat\DirnameDirConcatStringToDirectStringPathRector;
use Rector\Config\RectorConfig;
use Rector\Configuration\RectorConfigBuilder;
use Rector\Doctrine\TypedCollections\Rector\Class_\InitializeCollectionInConstructorRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\FlipAssertRector;
use Rector\Transform\Rector\Attribute\AttributeKeyToClassConstFetchRector;

require_once __DIR__.'/bootstrap.php';

/*
 * The shared Rector configuration. No version number is written here: `withPhpSets()` and `withComposerBased()` read
 * `composer.json`, so the version policy is the package's constraint and the tooling follows it.
 *
 * A project's `rector.php` calls the closure with its own directory; the paths come from the `phil-dev.php` of the
 * root and of each package, and each manifest's `rector.skip` adds its exceptions.
 *
 * @return Closure(string): RectorConfigBuilder
 */
return static function (string $root): RectorConfigBuilder {
    $tooling = Tooling::fromEnvironment($root);
    $domain = $tooling->layerPatterns('Domain');
    $models = $tooling->layerPatterns('Domain/Model');

    // A rule skipped on no path at all would be skipped everywhere; a rule a Rector release renamed or dropped is left
    // out rather than refused.
    $layered = array_filter([
        // Initialising Doctrine collections in the constructor would bring `Doctrine\Common\Collections` into a
        // `Domain` layer, where only the mapping attributes are admitted. The rule changed its name across releases.
        'Rector\\Doctrine\\CodeQuality\\Rector\\Class_\\InitializeDefaultEntityCollectionRector' => $domain,
        InitializeCollectionInConstructorRector::class => $domain,

        // Replacing a column type's name by a constant of `Doctrine\DBAL\Types\Types` would bring DBAL into
        // `Domain` for the same reason.
        AttributeKeyToClassConstFetchRector::class => $domain,

        // An entity of `Domain\Model` is never `readonly`, even when all its fields are: the unit of work of
        // Doctrine follows it in place.
        ReadOnlyClassRector::class => $models,
    ], static fn (array $paths, string $rule): bool => [] !== $paths && class_exists($rule), \ARRAY_FILTER_USE_BOTH);

    return RectorConfig::configure()
        ->withPaths([...$tooling->directories(), ...$tooling->files(), ...$tooling->configurationFiles()])
        ->withPhpSets()
        ->withComposerBased(doctrine: true, phpunit: true, symfony: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
            phpunitCodeQuality: true,
            doctrineCodeQuality: true,
            symfonyCodeQuality: true,
        )
        ->withAttributesSets(symfony: true)
        ->withImportNames(importShortClasses: false)
        ->withCache($root.'/var/rector')
        ->withParallel()
        ->withSkip([
            ...$layered,

            // `self::assertSame()` is a choice: the data providers are static, and a suite that always calls the same
            // way reads better than one that mixes `$this->` and `self::`.
            PreferPHPUnitThisCallRector::class,

            // The rule assumes the literal is the actual value; in `assertSame([…], Foo::SUPPORTED)` the literal is
            // what the test expects, and flipping the two would swap the halves of the failure message.
            FlipAssertRector::class,

            // `dirname(__DIR__).'/.env'` is the form of the Symfony Flex recipes; rewriting it makes each file diverge
            // from its recipe, which the next recipe update brings back.
            DirnameDirConcatStringToDirectStringPathRector::class,

            '*/var/*',
            '*/vendor/*',
            '*/vendor-bin/*',
            '*/tests/Fixtures/*',
            '*/tests/Witnesses/*',
            ...$tooling->rectorSkips(),
        ]);
};
