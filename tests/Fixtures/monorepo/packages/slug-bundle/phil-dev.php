<?php

declare(strict_types=1);

return [
    'paths' => ['src', 'tests', 'bin/console'],
    'cs' => ['exclude' => ['src/Generated']],
    'phpstan' => [
        'excludePaths' => ['src/Generated/*'],
        'ignoreErrors' => [
            ['identifier' => 'argument.type', 'path' => 'src/Legacy.php'],
            ['identifier' => 'return.type', 'paths' => ['src/A.php', '*/B.php']],
            '#^A message$#',
        ],
    ],
    'rector' => ['skip' => ['Some\\Rule', 'src/Skipped', 'Other\\Rule' => ['src/One.php']]],
    'infection' => [
        'excludes' => ['Infrastructure'],
        'mutators' => ['IncrementInteger' => ['ignoreSourceCodeByRegex' => ['private const int MAX_.*']]],
    ],
    'arkitect' => ['namespaces' => ['Legacy']],
];
