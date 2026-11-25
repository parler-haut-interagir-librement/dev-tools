<?php

declare(strict_types=1);

// The fixture needs no class but its own and those of PHP.
spl_autoload_register(static function (string $class): void {
    $file = dirname(__DIR__).'/src/'.str_replace(['App\\', '\\'], ['', '/'], $class).'.php';

    if (str_starts_with($class, 'App\\') && is_file($file)) {
        require $file;
    }
});
