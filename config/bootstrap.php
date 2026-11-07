<?php

declare(strict_types=1);

/*
 * Loads the classes of `phil/dev-tools` that the shared configurations read, inside each tool's own process.
 *
 * The tools run from `vendor-bin/`, with their own autoloaders: PHP-CS-Fixer, Arkitect and Deptrac never load the
 * project's. The classes they need here depend on nothing but PHP.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Phil\\DevTools\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = dirname(__DIR__).'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($file)) {
        require_once $file;
    }
});
