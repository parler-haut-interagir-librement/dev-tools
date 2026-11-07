<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this monorepo: the root's own paths and the globs of its packages, each of which keeps
 * its own phil-dev.php. See vendor/phil/dev-tools/doc/configuration.md for every section.
 */
return [
    'paths' => ['src', 'tests'],
    'packages' => ['packages/*'],
];
