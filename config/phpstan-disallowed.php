<?php

declare(strict_types=1);

use Composer\InstalledVersions;

/*
 * The sets of `spaze/phpstan-disallowed-calls` — dangerous, execution, insecure, loose and non-timing-safe calls — and
 * the rules of `phpstan-disallowed.neon`. The sets are found where the package is installed, in `vendor-bin/phpstan`
 * or wherever PHPStan's own autoloader found it, so that the include works at any depth under `vendor/`.
 */
$directory = null;

if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('spaze/phpstan-disallowed-calls')) {
    $path = InstalledVersions::getInstallPath('spaze/phpstan-disallowed-calls');
    $directory = null === $path ? null : realpath($path);
}

if (null === $directory || false === $directory) {
    $variable = getenv('PHIL_DEV_ROOT');
    $root = is_string($variable) && '' !== $variable ? $variable : (string) getcwd();
    $directory = $root.'/vendor-bin/phpstan/vendor/spaze/phpstan-disallowed-calls';
}

$includes = [];

foreach (['dangerous', 'execution', 'insecure', 'loose', 'non-timing-safe'] as $set) {
    $includes[] = $directory.'/disallowed-'.$set.'-calls.neon';
}

$includes[] = __DIR__.'/phpstan-disallowed.neon';

return ['includes' => $includes];
