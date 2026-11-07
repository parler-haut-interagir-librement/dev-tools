<?php

declare(strict_types=1);

use Rector\Configuration\RectorConfigBuilder;

/**
 * The shared Rector configuration of phil/dev-tools. The paths and skips come from phil-dev.php.
 *
 * @var Closure(string): RectorConfigBuilder $shared
 */
$shared = require __DIR__.'/{{config}}/rector.php';

return $shared(__DIR__);
