<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The declared dependencies, checked against the package installed on its own. The witnesses import on purpose what
 * the PHPStan rules refuse, and are never loaded; the fixtures are packages of their own.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToExclude(__DIR__.'/tests/Witnesses')
    ->addPathToExclude(__DIR__.'/tests/Fixtures')

    // The test helpers of `Testing` run inside a package's own tests, which require what they use: the recorder of
    // `symfony/http-client` and `symfony/phpunit-bridge` is suggested, and required here in development only, with the
    // contracts the client brings.
    ->ignoreErrorsOnPath(__DIR__.'/src/Testing', [ErrorType::DEV_DEPENDENCY_IN_PROD, ErrorType::SHADOW_DEPENDENCY])

    // The rules of `PhpStan` only ever run inside PHPStan, which each project installs in `vendor-bin/phpstan`, with the
    // parser it ships; it is required here in development only, for the tests of those rules.
    ->ignoreErrorsOnPath(__DIR__.'/src/PhpStan', [ErrorType::DEV_DEPENDENCY_IN_PROD, ErrorType::SHADOW_DEPENDENCY]);
