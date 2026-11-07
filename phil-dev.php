<?php

declare(strict_types=1);

use Phil\DevTools\Matrix\Version;

/*
 * What phil/dev-tools reads of itself: it is checked through its own shared configurations.
 */
return [
    'paths' => ['src', 'tests', 'config', 'bin/phil-dev'],
    'phpstan' => [
        // These three load the classes of PHP-CS-Fixer, Rector and Arkitect, which only their own vendor-bin has.
        'excludePaths' => ['config/arkitect.php', 'config/php-cs-fixer.php', 'config/rector.php'],
        'ignoreErrors' => [
            // The commands read PHIL_DEV_ROOT, PHIL_DEV_PACKAGES, PHIL_DEV_BASE_REF and the service variables from the
            // environment: their tests set each one, and restore it in a `finally`.
            ['identifier' => 'disallowed.function', 'message' => '#^Calling putenv\(\) is forbidden#', 'path' => 'tests/Unit/*'],
        ],
    ],
    'deptrac' => [
        // The recorder of HttpRecording is the PHPUnit bridge's: the helper only exists for the tests of packages.
        'allow' => ['^Symfony\\\\Bridge\\\\PhpUnit\\\\.*'],
    ],
    'infection' => [
        'mutators' => [
            // The two numbers of a version, matched as digits when the matrix is read, multiply the same as strings.
            'CastInt' => ['ignore' => [Version::class.'::id']],
        ],
    ],
];
