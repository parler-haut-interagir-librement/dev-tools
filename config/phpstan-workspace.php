<?php

declare(strict_types=1);

use Phil\DevTools\Config\Tooling;

require_once __DIR__.'/bootstrap.php';

/*
 * The paths of the root and of each package, with the exceptions their `phil-dev.php` declare, made absolute. The root
 * is `PHIL_DEV_ROOT`, or the directory PHPStan runs from. They reach PHPStan through a generated file, whose content
 * keys its cache.
 */
$tooling = Tooling::fromEnvironment();

return ['includes' => [$tooling->phpstanInclude('phpstan', $tooling->phpstan())]];
