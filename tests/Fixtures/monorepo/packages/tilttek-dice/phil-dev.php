<?php

declare(strict_types=1);

return [
    'infection' => [
        'directories' => ['src'],
        'mutators' => ['IncrementInteger' => ['ignoreSourceCodeByRegex' => ['return hrtime.*']], 'TrueValue' => false],
    ],
    'deptrac' => ['ruleset' => ['TilttekDice' => ['DefaultAllowed']]],
];
