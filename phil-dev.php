<?php

declare(strict_types=1);

use Phil\DevTools\Command\WorkspaceCommand;
use Phil\DevTools\Matrix\Version;
use Phil\DevTools\Process\LocalProcessRunner;

/*
 * What phil/dev-tools reads of itself: it is checked through its own shared configurations.
 */
return [
    'paths' => ['src', 'tests', 'config', 'bin/phil-dev'],
    'phpstan' => [
        // These load the classes of PHP-CS-Fixer, Rector, Arkitect and Twig, which only their own vendor-bin has.
        'excludePaths' => ['config/arkitect.php', 'config/php-cs-fixer.php', 'config/rector.php', 'config/twig-loader.php'],
        'ignoreErrors' => [
            // The port of the processes stays next to its two adapters, the local runner and the recording one.
            ['identifier' => 'symplify.requiredInterfaceContractNamespace', 'path' => 'src/Process/ProcessRunner.php'],
            // The commands read PHIL_DEV_ROOT, PHIL_DEV_PACKAGES, PHIL_DEV_BASE_REF and the service variables from the
            // environment: their tests set each one, and restore it in a `finally`.
            ['identifier' => 'disallowed.function', 'message' => '#^Calling putenv\(\) is forbidden#', 'path' => 'tests/Unit/*'],
        ],
    ],
    'deptrac' => [
        // The recorder of HttpRecording is the PHPUnit bridge's: the helper only exists for the tests of packages.
        // The member autoloader adds its prefixes to Composer's ClassLoader, which every Composer project holds.
        'allow' => ['^Symfony\\\\Bridge\\\\PhpUnit\\\\.*', '^Composer\\\\Autoload\\\\ClassLoader$'],
    ],
    'infection' => [
        'mutators' => [
            // The two numbers of a version, matched as digits when the matrix is read, multiply the same as strings.
            'CastInt' => ['ignore' => [Version::class.'::id']],
            // The root a command runs on exists, so realpath() never returns false for it.
            'CastString' => ['ignore' => [WorkspaceCommand::class.'::testEnvironment']],
            // A timeout a nanosecond longer or shorter, reached at its instant or the one after, cannot be told apart.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*\$timeout \* 1_000_000_000;', '.*\(hrtime\(true\) - \$start\) / 1_000_000;']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*\$timeout \* 1_000_000_000;', '.*\(hrtime\(true\) - \$start\) / 1_000_000;']],
            'GreaterThanOrEqualTo' => ['ignore' => [LocalProcessRunner::class.'::run']],
            // Closing a finished process frees its resource, and the pause between two looks spares the processor: a
            // test sees neither.
            'FunctionCallRemoval' => ['ignoreSourceCodeByRegex' => ['proc_close\(\$process\);', 'usleep\(self::POLL_MICROSECONDS\);']],
        ],
    ],
];
