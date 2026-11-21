<?php

declare(strict_types=1);

use Phil\DevTools\Command\WorkspaceCommand;
use Phil\DevTools\Config\PhpUnitConfig;
use Phil\DevTools\Matrix\Version;
use Phil\DevTools\Process\LocalProcessRunner;

/*
 * What phil/dev-tools reads of itself: it is checked through its own shared configurations.
 */
return [
    'paths' => ['src', 'tests', 'config', 'bin/phil-dev'],
    'phpstan' => [
        // These load the classes of PHP-CS-Fixer, Rector, Arkitect and Twig, which only their own vendor-bin has.
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`. The script of lint-apps runs with
        // the autoloader of the workspace it lints, whose Symfony kernel and console this package does not require.
        'excludePaths' => ['config/arkitect.php', 'config/php-cs-fixer.php', 'config/rector.php', 'config/twig-loader.php', 'tests/Fuzz/*', 'resources/lint/app.php'],
        'ignoreErrors' => [
            // The commands read PHIL_DEV_ROOT, PHIL_DEV_PACKAGES, PHIL_DEV_BASE_REF and the service variables from the
            // environment: their tests set each one, and restore it in a `finally`.
            ['identifier' => 'disallowed.function', 'message' => '#^Calling putenv\(\) is forbidden#', 'path' => 'tests/Unit/*'],
        ],
    ],
    'deptrac' => [
        // The recorder of HttpRecording is the PHPUnit bridge's: the helper only exists for the tests of packages.
        // The member autoloader adds its prefixes to Composer's ClassLoader, which every Composer project holds.
        // The PHPStan rules of this package run inside PHPStan, on the classes of its parser. The rule on logger calls
        // names the PSR-3 interface by its `::class`, a string that loads nothing.
        'allow' => ['^Symfony\\\\Bridge\\\\PhpUnit\\\\.*', '^Composer\\\\Autoload\\\\ClassLoader$', '^PHPStan\\\\.*', '^PhpParser\\\\.*', '^Psr\\\\Log\\\\LoggerInterface$'],
    ],
    // The tooling's own documentation has no French mirror yet.
    'docs' => ['translations' => 'advisory', 'public' => true],
    'fuzz' => [
        // The reader of the kernels of functional test applications and the rules of a Composer archive, from the
        // seeds of tests/Fuzz/corpus/<target>.
        'targets' => ['test-kernels' => 'tests/Fuzz/test-kernels.php', 'archive-rules' => 'tests/Fuzz/archive-rules.php'],
    ],
    'infection' => [
        'mutators' => [
            // The two numbers of a version, matched as digits when the matrix is read, multiply the same as strings.
            'CastInt' => ['ignore' => [Version::class.'::id']],
            // The root a command runs on exists, so realpath() never returns false for it; a document DOM has loaded
            // always saves.
            'CastString' => ['ignore' => [WorkspaceCommand::class.'::testEnvironment', PhpUnitConfig::class.'::generated']],
            // The elements of a document are read into a list whose keys are 0, 1, 2… either way: preserving them changes nothing.
            'FalseValue' => ['ignore' => [PhpUnitConfig::class.'::expand']],
            // A timeout a nanosecond longer or shorter, reached at its instant or the one after, cannot be told apart.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*\$timeout \* 1_000_000_000;', '.*\(hrtime\(true\) - \$start\) / 1_000_000;']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*\$timeout \* 1_000_000_000;', '.*\(hrtime\(true\) - \$start\) / 1_000_000;']],
            'GreaterThanOrEqualTo' => ['ignore' => [LocalProcessRunner::class.'::run']],
            // A type that is all objects is never an array too: before the arrays of a context are read, nothing has been
            // found, so keeping it or not changes nothing.
            'ArrayItemRemoval' => ['ignoreSourceCodeByRegex' => ['.*\$found = \[\.\.\.\$found, \.\.\.\$this->objectsIn\(\$array->getIterableValueType\(\).*']],
            // Closing a finished process frees its resource, and the pause between two looks spares the processor: a
            // test sees neither.
            'FunctionCallRemoval' => ['ignoreSourceCodeByRegex' => ['proc_close\(\$process\);', 'usleep\(self::POLL_MICROSECONDS\);']],
        ],
    ],
];
