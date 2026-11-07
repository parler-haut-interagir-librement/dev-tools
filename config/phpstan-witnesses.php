<?php

declare(strict_types=1);

use Phil\DevTools\Config\Tooling;
use Phil\DevTools\Matrix\Matrix;

require_once __DIR__.'/bootstrap.php';

/*
 * The witness directories of the root and of each package, and a temporary directory of their own. They reach PHPStan
 * through a generated file, whose content keys its cache.
 */
$tooling = Tooling::fromEnvironment();

return ['includes' => [$tooling->phpstanInclude('phpstan-witnesses', ['parameters' => [
    'phpVersion' => Matrix::load()->floor('php')->id(),
    'paths' => $tooling->witnessDirectories(),
    'tmpDir' => $tooling->workspace->root->path('var/phpstan-witnesses'),
]])]];
