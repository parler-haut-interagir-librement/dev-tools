<?php

declare(strict_types=1);

use PhpCsFixer\ConfigInterface;

/**
 * The shared code style of phil/dev-tools. The paths come from phil-dev.php; pass rules here to add to or change it.
 *
 * @var Closure(string, array<string, mixed>=): ConfigInterface $shared
 */
$shared = require __DIR__.'/{{config}}/php-cs-fixer.php';

return $shared(__DIR__);
