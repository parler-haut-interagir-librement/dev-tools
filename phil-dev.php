<?php

declare(strict_types=1);

use Phil\DevTools\Command\InfectionCommand;
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
        // the autoloader of the workspace it lints, whose Symfony kernel and console this package does not require. The
        // common Castor tasks run inside Castor, which gives them its functions; what they decide is in `src/Castor`.
        'excludePaths' => ['resources/castor/tasks.php', 'config/arkitect.php', 'config/php-cs-fixer.php', 'config/rector.php', 'config/twig-loader.php', 'tests/Fuzz/*', 'resources/lint/app.php'],
    ],
    'deptrac' => [
        // The recorder of HttpRecording is the PHPUnit bridge's: the helper only exists for the tests of packages.
        // The member autoloader adds its prefixes to Composer's ClassLoader, which every Composer project holds.
        // The PHPStan rules of this package run inside PHPStan, on the classes of its parser. The rule on logger calls
        // names the PSR-3 interface by its `::class`, a string that loads nothing. The route smoke test is a trait of the
        // test cases of packages, which name its data provider with the attribute of PHPUnit.
        'allow' => ['^Symfony\\\\Bridge\\\\PhpUnit\\\\.*', '^PHPUnit\\\\Framework\\\\Attributes\\\\.*', '^Composer\\\\Autoload\\\\ClassLoader$', '^PHPStan\\\\.*', '^PhpParser\\\\.*', '^Psr\\\\Log\\\\LoggerInterface$'],
    ],
    // The tooling's own documentation has no French mirror yet.
    'docs' => ['translations' => 'advisory', 'public' => true],
    'fuzz' => [
        // The reader of the kernels of functional test applications, the rules of a Composer archive, the regex
        // generator and what merge-lot reads of its lock and of git, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => ['test-kernels' => 'tests/Fuzz/test-kernels.php', 'archive-rules' => 'tests/Fuzz/archive-rules.php', 'regex-generator' => 'tests/Fuzz/regex-generator.php', 'merge-lock' => 'tests/Fuzz/merge-lock.php'],
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
            // A Unicode property is kept without `u` when its range ends in printable ASCII: each range of the table lies
            // wholly inside it or wholly beyond it, and none ends on the last printable character, so its start tells the
            // same as its end, and `<` the same as `<=`.
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*\$timeout \* 1_000_000_000;', '.*\(hrtime\(true\) - \$start\) / 1_000_000;', '.*\$range\[1\] <= self::LAST\).*']],
            'LessThanOrEqualTo' => ['ignoreSourceCodeByRegex' => ['.*\$range\[1\] <= self::LAST\).*']],
            'GreaterThanOrEqualTo' => ['ignore' => [LocalProcessRunner::class.'::run']],
            // A type that is all objects is never an array too: before the arrays of a context are read, nothing has been
            // found, so keeping it or not changes nothing.
            'ArrayItemRemoval' => ['ignoreSourceCodeByRegex' => ['.*\$found = \[\.\.\.\$found, \.\.\.\$this->objectsIn\(\$array->getIterableValueType\(\).*']],
            // Closing a finished process frees its resource, and the pause between two looks spares the processor: a
            // test sees neither. The status cache of PHP is cleared before the lock's time is read, against a lock another
            // merge moved since the last look; PHPUnit's process keeps no such cache across a file changed from outside,
            // so no test can tell it from its absence.
            // A package on its own is reloaded as itself, its own name for the only package analysed, which is all it holds either way;
            // and the names reach a test that only asks whether one is among them, as a list or as a map.
            'ReturnRemoval' => ['ignore' => [InfectionCommand::class.'::owners']],
            'UnwrapArrayValues' => ['ignore' => [InfectionCommand::class.'::owners']],
            // The members are sorted by directory, so a member whose directory is the start of another's comes first and the
            // longer one, tested later, takes the file back: the slash only keeps the first from being the last match.
            'ConcatOperandRemoval' => ['ignore' => [InfectionCommand::class.'::owners']],
            'FunctionCallRemoval' => ['ignoreSourceCodeByRegex' => ['proc_close\(\$process\);', 'usleep\(self::POLL_MICROSECONDS\);', 'clearstatcache\(true, \$this->path\);']],
        ],
    ],
];
