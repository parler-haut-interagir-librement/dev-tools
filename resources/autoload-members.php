<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Phil\DevTools\Workspace\MemberAutoloader;

/*
 * Loads the classes of every member of the monorepo from their own composer.json. Composer includes this file from
 * the root's autoload-dev, and names the root itself.
 */
$root = realpath(InstalledVersions::getRootPackage()['install_path']);

if (false !== $root && class_exists(MemberAutoloader::class)) {
    MemberAutoloader::register($root);
}
