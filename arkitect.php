<?php

declare(strict_types=1);

use Arkitect\CLI\Config;

/**
 * The shared shape rules of phil/dev-tools. The classes come from composer.json; extra rules go in phil-dev.php.
 *
 * @var Closure(string): (Closure(Config): void) $shared
 */
$shared = require __DIR__.'/config/arkitect.php';

return $shared(__DIR__);
